<?php

/**
 * @file
 * Database additions for Drupal\contact\Tests\ContactUpgradePathTest.
 *
 * This dump only contains data for the contact module. The
 * drupal-7.filled.bare.php file is imported before this dump, so the two form
 * the database structure expected in tests altogether.
 */

declare(strict_types=1);

$connection = \Drupal::database();
// Update the default category to that it is not selected.
$connection->update('contact')
  ->fields(['selected' => '0'])
  ->condition('cid', '1')
  ->execute();

// Add a custom contact category.
$connection->insert('contact')->fields([
  'category',
  'recipients',
  'reply',
  'weight',
  'selected',
])
  ->values([
    'category' => 'Upgrade test',
    'recipients' => 'test1@example.com,test2@example.com',
    'reply' => 'Test reply',
    'weight' => 1,
    'selected' => 1,
  ])
  ->execute();
