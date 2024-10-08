<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional;

use Drupal\Tests\rest\Functional\CookieResourceTestTrait;
use Drupal\Tests\rest\Functional\FileUploadResourceTestBase;

/**
 * @group file
 */
class FileUploadJsonCookieTest extends FileUploadResourceTestBase {

  use CookieResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'cookie';

  /**
   * Entity type ID for this storage.
   *
   * @var string
   */
  protected static string $entityTypeId;

}
