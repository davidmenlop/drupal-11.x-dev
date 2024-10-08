<?php

declare(strict_types=1);

namespace Drupal\Tests\path_alias\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\path_alias\AliasManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\path_alias\AliasManager
 * @group path_alias
 */
class AliasManagerTest extends UnitTestCase {

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $aliasManager;

  /**
   * Alias repository.
   *
   * @var \Drupal\path_alias\AliasRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aliasRepository;

  /**
   * Alias prefix list.
   *
   * @var \Drupal\path_alias\AliasPrefixListInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aliasPrefixList;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cache;

  /**
   * The internal cache key used by the alias manager.
   *
   * @var string
   */
  protected $cacheKey = 'preload-paths:key';

  /**
   * The cache key passed to the alias manager.
   *
   * @var string
   */
  protected $path = 'key';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->aliasRepository = $this->createMock(AliasRepositoryInterface::class);
    $this->aliasPrefixList = $this->createMock('Drupal\path_alias\AliasPrefixListInterface');
    $this->languageManager = $this->createMock('Drupal\Core\Language\LanguageManagerInterface');
    $this->cache = $this->createMock('Drupal\Core\Cache\CacheBackendInterface');

    $this->aliasManager = new AliasManager($this->aliasRepository, $this->aliasPrefixList, $this->languageManager, $this->cache, new Time());

  }

  /**
   * Tests the getPathByAlias method for an alias that have no matching path.
   *
   * @covers ::getPathByAlias
   */
  public function testGetPathByAliasNoMatch(): void {
    $alias = '/' . $this->randomMachineName();

    $language = new Language(['id' => 'en']);

    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_URL)
      ->willReturn($language);

    $this->aliasRepository->expects($this->once())
      ->method('lookupByAlias')
      ->with($alias, $language->getId())
      ->willReturn(NULL);

    $this->assertEquals($alias, $this->aliasManager->getPathByAlias($alias));
    // Call it twice to test the static cache.
    $this->assertEquals($alias, $this->aliasManager->getPathByAlias($alias));
  }

  /**
   * Tests the getPathByAlias method for an alias that have a matching path.
   *
   * @covers ::getPathByAlias
   */
  public function testGetPathByAliasMatch(): void {
    $alias = $this->randomMachineName();
    $path = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();

    $this->aliasRepository->expects($this->once())
      ->method('lookupByAlias')
      ->with($alias, $language->getId())
      ->willReturn(['path' => $path]);

    $this->assertEquals($path, $this->aliasManager->getPathByAlias($alias));
    // Call it twice to test the static cache.
    $this->assertEquals($path, $this->aliasManager->getPathByAlias($alias));
  }

  /**
   * Tests the getPathByAlias method when a langcode is passed explicitly.
   *
   * @covers ::getPathByAlias
   */
  public function testGetPathByAliasLangcode(): void {
    $alias = $this->randomMachineName();
    $path = $this->randomMachineName();

    $this->languageManager->expects($this->never())
      ->method('getCurrentLanguage');

    $this->aliasRepository->expects($this->once())
      ->method('lookupByAlias')
      ->with($alias, 'de')
      ->willReturn(['path' => $path]);

    $this->assertEquals($path, $this->aliasManager->getPathByAlias($alias, 'de'));
    // Call it twice to test the static cache.
    $this->assertEquals($path, $this->aliasManager->getPathByAlias($alias, 'de'));
  }

  /**
   * Tests the getAliasByPath method for a path that is not in the prefix list.
   *
   * @covers ::getAliasByPath
   */
  public function testGetAliasByPathPrefixList() {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;

    $this->setUpCurrentLanguage();

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(FALSE);

    // The prefix list returns FALSE for that path part, so the storage should
    // never be called.
    $this->aliasRepository->expects($this->never())
      ->method('lookupBySystemPath');

    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));
  }

  /**
   * Tests the getAliasByPath method for a path that has no matching alias.
   *
   * @covers ::getAliasByPath
   */
  public function testGetAliasByPathNoMatch(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;

    $language = $this->setUpCurrentLanguage();

    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    $this->aliasRepository->expects($this->once())
      ->method('lookupBySystemPath')
      ->with($path, $language->getId())
      ->willReturn(NULL);

    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));

    // This needs to write out the cache.
    $this->cache->expects($this->once())
      ->method('set')
      ->with($this->cacheKey, [$language->getId() => [$path]], (int) $_SERVER['REQUEST_TIME'] + (60 * 60 * 24));

    $this->aliasManager->writeCache();
  }

  /**
   * Tests the getAliasByPath method exception.
   *
   * @covers ::getAliasByPath
   */
  public function testGetAliasByPathException(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->aliasManager->getAliasByPath('no-leading-slash-here');
  }

  /**
   * Tests the getAliasByPath method for a path that has a matching alias.
   *
   * @covers ::getAliasByPath
   * @covers ::writeCache
   */
  public function testGetAliasByPathMatch(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;
    $alias = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();

    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    $this->aliasRepository->expects($this->once())
      ->method('lookupBySystemPath')
      ->with($path, $language->getId())
      ->willReturn(['alias' => $alias]);

    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path));

    // This needs to write out the cache.
    $this->cache->expects($this->once())
      ->method('set')
      ->with($this->cacheKey, [$language->getId() => [$path]], (int) $_SERVER['REQUEST_TIME'] + (60 * 60 * 24));

    $this->aliasManager->writeCache();
  }

  /**
   * Tests the getAliasByPath method for a path that is preloaded.
   *
   * @covers ::getAliasByPath
   * @covers ::writeCache
   */
  public function testGetAliasByPathCachedMatch(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;
    $alias = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();

    // Use a set of cached paths where the tested path is in any position, not
    // only in the first one.
    $cached_paths = [
      $language->getId() => [
        '/another/path',
        $path,
      ],
    ];
    $this->cache->expects($this->once())
      ->method('get')
      ->with($this->cacheKey)
      ->willReturn((object) ['data' => $cached_paths]);

    // Simulate a request so that the preloaded paths are fetched.
    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    $this->aliasRepository->expects($this->once())
      ->method('preloadPathAlias')
      ->with($cached_paths[$language->getId()], $language->getId())
      ->willReturn([$path => $alias]);

    // LookupPathAlias should not be called.
    $this->aliasRepository->expects($this->never())
      ->method('lookupBySystemPath');

    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path));

    // This must not write to the cache again.
    $this->cache->expects($this->never())
      ->method('set');
    $this->aliasManager->writeCache();
  }

  /**
   * Tests the getAliasByPath cache when a different language is requested.
   *
   * @covers ::getAliasByPath
   * @covers ::writeCache
   */
  public function testGetAliasByPathCachedMissLanguage(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;
    $alias = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();
    $cached_language = new Language(['id' => 'de']);

    $cached_paths = [$cached_language->getId() => [$path]];
    $this->cache->expects($this->once())
      ->method('get')
      ->with($this->cacheKey)
      ->willReturn((object) ['data' => $cached_paths]);

    // Simulate a request so that the preloaded paths are fetched.
    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    // The requested language is different than the cached, so this will
    // need to load.
    $this->aliasRepository->expects($this->never())
      ->method('preloadPathAlias');
    $this->aliasRepository->expects($this->once())
      ->method('lookupBySystemPath')
      ->with($path, $language->getId())
      ->willReturn(['alias' => $alias]);

    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path));

    // There is already a cache entry, so this should not write out to the
    // cache.
    $this->cache->expects($this->never())
      ->method('set');
    $this->aliasManager->writeCache();
  }

  /**
   * Tests the getAliasByPath cache with a preloaded path without alias.
   *
   * @covers ::getAliasByPath
   * @covers ::writeCache
   */
  public function testGetAliasByPathCachedMissNoAlias(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;
    $cached_path = $this->randomMachineName();
    $cached_alias = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();

    $cached_paths = [$language->getId() => [$cached_path, $path]];
    $this->cache->expects($this->once())
      ->method('get')
      ->with($this->cacheKey)
      ->willReturn((object) ['data' => $cached_paths]);

    // Simulate a request so that the preloaded paths are fetched.
    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    $this->aliasRepository->expects($this->once())
      ->method('preloadPathAlias')
      ->with($cached_paths[$language->getId()], $language->getId())
      ->willReturn([$cached_path => $cached_alias]);

    // LookupPathAlias() should not be called.
    $this->aliasRepository->expects($this->never())
      ->method('lookupBySystemPath');

    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));

    // This must not write to the cache again.
    $this->cache->expects($this->never())
      ->method('set');
    $this->aliasManager->writeCache();
  }

  /**
   * Tests the getAliasByPath cache with an un-preloaded path without alias.
   *
   * @covers ::getAliasByPath
   * @covers ::writeCache
   */
  public function testGetAliasByPathUncachedMissNoAlias(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;
    $cached_path = $this->randomMachineName();
    $cached_alias = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();

    $cached_paths = [$language->getId() => [$cached_path]];
    $this->cache->expects($this->once())
      ->method('get')
      ->with($this->cacheKey)
      ->willReturn((object) ['data' => $cached_paths]);

    // Simulate a request so that the preloaded paths are fetched.
    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    $this->aliasRepository->expects($this->once())
      ->method('preloadPathAlias')
      ->with($cached_paths[$language->getId()], $language->getId())
      ->willReturn([$cached_path => $cached_alias]);

    $this->aliasRepository->expects($this->once())
      ->method('lookupBySystemPath')
      ->with($path, $language->getId())
      ->willReturn(NULL);

    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($path, $this->aliasManager->getAliasByPath($path));

    // There is already a cache entry, so this should not write out to the
    // cache.
    $this->cache->expects($this->never())
      ->method('set');
    $this->aliasManager->writeCache();
  }

  /**
   * @covers ::cacheClear
   */
  public function testCacheClear(): void {
    $path = '/path';
    $alias = '/alias';
    $language = $this->setUpCurrentLanguage();
    $this->aliasRepository->expects($this->exactly(2))
      ->method('lookupBySystemPath')
      ->with($path, $language->getId())
      ->willReturn(['alias' => $alias]);
    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->willReturn(TRUE);

    // Populate the lookup map.
    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path, $language->getId()));

    // Check that the cache is populated.
    $this->aliasRepository->expects($this->never())
      ->method('lookupByAlias');
    $this->assertEquals($path, $this->aliasManager->getPathByAlias($alias, $language->getId()));

    // Clear specific source.
    $this->aliasManager->cacheClear($path);

    // Ensure cache has been cleared (this will be the 2nd call to
    // `lookupPathAlias` if cache is cleared).
    $this->assertEquals($alias, $this->aliasManager->getAliasByPath($path, $language->getId()));

    // Clear non-existent source.
    $this->aliasManager->cacheClear('non-existent');
  }

  /**
   * Tests the getAliasByPath cache with an un-preloaded path with alias.
   *
   * @covers ::getAliasByPath
   * @covers ::writeCache
   */
  public function testGetAliasByPathUncachedMissWithAlias(): void {
    $path_part1 = $this->randomMachineName();
    $path_part2 = $this->randomMachineName();
    $path = '/' . $path_part1 . '/' . $path_part2;
    $cached_path = $this->randomMachineName();
    $cached_no_alias_path = $this->randomMachineName();
    $cached_alias = $this->randomMachineName();
    $new_alias = $this->randomMachineName();

    $language = $this->setUpCurrentLanguage();

    $cached_paths = [$language->getId() => [$cached_path, $cached_no_alias_path]];
    $this->cache->expects($this->once())
      ->method('get')
      ->with($this->cacheKey)
      ->willReturn((object) ['data' => $cached_paths]);

    // Simulate a request so that the preloaded paths are fetched.
    $this->aliasManager->setCacheKey($this->path);

    $this->aliasPrefixList->expects($this->any())
      ->method('get')
      ->with($path_part1)
      ->willReturn(TRUE);

    $this->aliasRepository->expects($this->once())
      ->method('preloadPathAlias')
      ->with($cached_paths[$language->getId()], $language->getId())
      ->willReturn([$cached_path => $cached_alias]);

    $this->aliasRepository->expects($this->once())
      ->method('lookupBySystemPath')
      ->with($path, $language->getId())
      ->willReturn(['alias' => $new_alias]);

    $this->assertEquals($new_alias, $this->aliasManager->getAliasByPath($path));
    // Call it twice to test the static cache.
    $this->assertEquals($new_alias, $this->aliasManager->getAliasByPath($path));

    // There is already a cache entry, so this should not write out to the
    // cache.
    $this->cache->expects($this->never())
      ->method('set');
    $this->aliasManager->writeCache();
  }

  /**
   * Sets up the current language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The current language object.
   */
  protected function setUpCurrentLanguage() {
    $language = new Language(['id' => 'en']);

    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_URL)
      ->willReturn($language);

    return $language;
  }

}
