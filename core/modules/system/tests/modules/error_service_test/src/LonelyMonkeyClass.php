<?php

declare(strict_types=1);

namespace Drupal\error_service_test;

use Drupal\Core\Database\Connection;

/**
 * A class with a single dependency.
 */
class LonelyMonkeyClass {

  /**
   * The database connection.
   */
  protected Connection $connection;

  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

}
