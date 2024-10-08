<?php

/**
 * @file
 * Provides views data and hooks for views_test_data module.
 */

declare(strict_types=1);

use Drupal\views\ViewExecutable;
use Drupal\views\Analyzer;

/**
 * Implements hook_views_data().
 */
function views_test_data_views_data() {
  $state = \Drupal::service('state');
  $state->set('views_hook_test_views_data', TRUE);
  // We use a state variable to keep track of how many times this function is
  // called so we can assert that calls to
  // \Drupal\views\ViewsData::delete() trigger a rebuild of views data.
  if (!($count = $state->get('views_test_data_views_data_count'))) {
    $count = 0;
  }
  $count++;
  $state->set('views_test_data_views_data_count', $count);

  return $state->get('views_test_data_views_data', []);
}

/**
 * Implements hook_views_data_alter().
 */
function views_test_data_views_data_alter(array &$data) {
  \Drupal::state()->set('views_hook_test_views_data_alter', TRUE);
  \Drupal::state()->set('views_hook_test_views_data_alter_data', $data);
}

/**
 * Implements hook_views_analyze().
 */
function views_test_data_views_analyze(ViewExecutable $view) {
  \Drupal::state()->set('views_hook_test_views_analyze', TRUE);

  $ret = [];

  $ret[] = Analyzer::formatMessage(t('Test ok message'), 'ok');
  $ret[] = Analyzer::formatMessage(t('Test warning message'), 'warning');
  $ret[] = Analyzer::formatMessage(t('Test error message'), 'error');

  return $ret;
}

/**
 * Implements hook_views_invalidate_cache().
 */
function views_test_data_views_invalidate_cache() {
  \Drupal::state()->set('views_hook_test_views_invalidate_cache', TRUE);
}
