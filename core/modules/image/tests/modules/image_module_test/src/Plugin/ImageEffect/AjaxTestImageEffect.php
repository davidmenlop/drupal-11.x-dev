<?php

declare(strict_types=1);

namespace Drupal\image_module_test\Plugin\ImageEffect;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Provides a test effect using Ajax in the configuration form.
 */
#[ImageEffect(
  id: "image_module_test_ajax",
  label: new TranslatableMarkup("Ajax test")
)]
class AjaxTestImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'test_parameter' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['test_parameter'] = [
      '#type' => 'number',
      '#title' => $this->t('Test parameter'),
      '#default_value' => $this->configuration['test_parameter'],
      '#min' => 0,
    ];
    $form['ajax_refresh'] = [
      '#type' => 'button',
      '#value' => $this->t('Ajax refresh'),
      '#ajax' => ['callback' => [$this, 'ajaxCallback']],
    ];
    $form['ajax_value'] = [
      '#id' => 'ajax-value',
      '#type' => 'item',
      '#title' => $this->t('Ajax value'),
      '#markup' => 'bar',
    ];
    return $form;
  }

  /**
   * AJAX callback.
   */
  public function ajaxCallback($form, FormStateInterface $form_state) {
    $item = [
      '#type' => 'item',
      '#title' => $this->t('Ajax value'),
      '#markup' => microtime(),
    ];
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ajax-value', $item));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['test_parameter'] = $form_state->getValue('test_parameter');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return TRUE;
  }

}
