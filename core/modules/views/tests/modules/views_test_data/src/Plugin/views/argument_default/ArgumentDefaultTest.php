<?php

declare(strict_types=1);

namespace Drupal\views_test_data\Plugin\views\argument_default;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Defines an argument default test plugin.
 */
#[ViewsArgumentDefault(
  id: 'argument_default_test',
  title: new TranslatableMarkup('Argument default test'),
)]
class ArgumentDefaultTest extends ArgumentDefaultPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $this->view->element['#cache']['tags'][] = 'example_tag';
    return $this->options['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'content' => ['ArgumentDefaultTest'],
    ];
  }

}
