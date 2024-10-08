<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\Discovery\HookDiscovery;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Plugin\Discovery\HookDiscovery
 * @group Plugin
 */
class HookDiscoveryTest extends UnitTestCase {

  /**
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The tested hook discovery.
   *
   * @var \Drupal\Core\Plugin\Discovery\HookDiscovery
   */
  protected $hookDiscovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->hookDiscovery = new HookDiscovery($this->moduleHandler, 'test_plugin');
  }

  /**
   * Tests the getDefinitions() method without any plugins.
   *
   * @see \Drupal\Core\Plugin\Discovery::getDefinitions()
   */
  public function testGetDefinitionsWithoutPlugins(): void {
    $this->assertCount(0, $this->hookDiscovery->getDefinitions());
  }

  /**
   * Tests the getDefinitions() method with some plugins.
   *
   * @see \Drupal\Core\Plugin\Discovery::getDefinitions()
   */
  public function testGetDefinitions(): void {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('invokeAllWith')
      ->with('test_plugin')
      ->willReturnCallback(function (string $hook, callable $callback) {
        $callback(\Closure::fromCallable([$this, 'hookDiscoveryTestTestPlugin']), 'hook_discovery_test');
        $callback(\Closure::fromCallable([$this, 'hookDiscoveryTest2TestPlugin']), 'hook_discovery_test2');
      });
    $this->moduleHandler->expects($this->never())
      ->method('invoke');

    $definitions = $this->hookDiscovery->getDefinitions();

    $this->assertCount(3, $definitions);
    $this->assertEquals('Drupal\plugin_test\Plugin\plugin_test\fruit\Apple', $definitions['test_id_1']['class']);
    $this->assertEquals('Drupal\plugin_test\Plugin\plugin_test\fruit\Orange', $definitions['test_id_2']['class']);
    $this->assertEquals('Drupal\plugin_test\Plugin\plugin_test\fruit\Cherry', $definitions['test_id_3']['class']);

    // Ensure that the module was set.
    $this->assertEquals('hook_discovery_test', $definitions['test_id_1']['provider']);
    $this->assertEquals('hook_discovery_test', $definitions['test_id_2']['provider']);
    $this->assertEquals('hook_discovery_test2', $definitions['test_id_3']['provider']);
  }

  /**
   * Tests the getDefinition method with some plugins.
   *
   * @see \Drupal\Core\Plugin\Discovery::getDefinition()
   */
  public function testGetDefinition(): void {
    $this->moduleHandler->expects($this->exactly(4))
      ->method('invokeAllWith')
      ->with('test_plugin')
      ->willReturnCallback(function (string $hook, callable $callback) {
        $callback(\Closure::fromCallable([$this, 'hookDiscoveryTestTestPlugin']), 'hook_discovery_test');
        $callback(\Closure::fromCallable([$this, 'hookDiscoveryTest2TestPlugin']), 'hook_discovery_test2');
      });

    $this->assertNull($this->hookDiscovery->getDefinition('test_non_existent', FALSE));

    $plugin_definition = $this->hookDiscovery->getDefinition('test_id_1');
    $this->assertEquals('Drupal\plugin_test\Plugin\plugin_test\fruit\Apple', $plugin_definition['class']);
    $this->assertEquals('hook_discovery_test', $plugin_definition['provider']);

    $plugin_definition = $this->hookDiscovery->getDefinition('test_id_2');
    $this->assertEquals('Drupal\plugin_test\Plugin\plugin_test\fruit\Orange', $plugin_definition['class']);
    $this->assertEquals('hook_discovery_test', $plugin_definition['provider']);

    $plugin_definition = $this->hookDiscovery->getDefinition('test_id_3');
    $this->assertEquals('Drupal\plugin_test\Plugin\plugin_test\fruit\Cherry', $plugin_definition['class']);
    $this->assertEquals('hook_discovery_test2', $plugin_definition['provider']);
  }

  /**
   * Tests the getDefinition method with an unknown plugin ID.
   *
   * @see \Drupal\Core\Plugin\Discovery::getDefinition()
   */
  public function testGetDefinitionWithUnknownID(): void {
    $this->expectException(PluginNotFoundException::class);
    $this->hookDiscovery->getDefinition('test_non_existent', TRUE);
  }

  protected function hookDiscoveryTestTestPlugin(): array {
    return [
      'test_id_1' => ['class' => 'Drupal\plugin_test\Plugin\plugin_test\fruit\Apple'],
      'test_id_2' => ['class' => 'Drupal\plugin_test\Plugin\plugin_test\fruit\Orange'],
    ];
  }

  protected function hookDiscoveryTest2TestPlugin(): array {
    return [
      'test_id_3' => ['class' => 'Drupal\plugin_test\Plugin\plugin_test\fruit\Cherry'],
    ];
  }

}
