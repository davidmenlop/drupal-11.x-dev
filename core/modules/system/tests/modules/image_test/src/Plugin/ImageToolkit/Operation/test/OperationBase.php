<?php

declare(strict_types=1);

namespace Drupal\image_test\Plugin\ImageToolkit\Operation\test;

use Drupal\Core\ImageToolkit\ImageToolkitOperationBase;

/**
 * Provides a base class for test operations.
 */
abstract class OperationBase extends ImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  public function arguments() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments) {
    // Nothing to do.
    return TRUE;
  }

}
