<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File;

use Drupal\Core\File\Exception\NotRegularFileException;

/**
 * Tests the unmanaged file delete function.
 *
 * @group File
 */
class FileDeleteTest extends FileTestBase {

  /**
   * Delete a normal file.
   */
  public function testNormal(): void {
    // Create a file for testing
    $uri = $this->createUri();

    // Delete a regular file
    $this->assertTrue(\Drupal::service('file_system')->delete($uri), 'Deleted worked.');
    $this->assertFileDoesNotExist($uri);
  }

  /**
   * Try deleting a missing file.
   */
  public function testMissing(): void {
    // Try to delete a non-existing file
    $this->assertTrue(\Drupal::service('file_system')->delete('public://' . $this->randomMachineName()), 'Returns true when deleting a non-existent file.');
  }

  /**
   * Try deleting a directory.
   */
  public function testDirectory(): void {
    // A directory to operate on.
    $directory = $this->createDirectory();

    // Try to delete a directory.
    try {
      \Drupal::service('file_system')->delete($directory);
      $this->fail('Expected NotRegularFileException');
    }
    catch (NotRegularFileException) {
      // Ignore.
    }
    $this->assertDirectoryExists($directory);
  }

}
