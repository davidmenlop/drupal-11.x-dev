<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a simple form to test the #group property on #type 'vertical_tabs'.
 *
 * @internal
 */
class FormTestGroupVerticalTabsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_group_vertical_tabs';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['vertical_tabs'] = [
      '#type' => 'vertical_tabs',
    ];
    $form['meta'] = [
      '#type' => 'details',
      '#title' => 'First group element',
      '#group' => 'vertical_tabs',
    ];
    $form['meta']['element'] = [
      '#type' => 'textfield',
      '#title' => 'First nested element in details element',
    ];
    $form['meta_2'] = [
      '#type' => 'details',
      '#title' => 'Second group element',
      '#group' => 'vertical_tabs',
    ];
    $form['meta_2']['element_2'] = [
      '#type' => 'textfield',
      '#title' => 'Second nested element in details element',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('element_2') === 'bad') {
      $form_state->setErrorByName('element_2', $this->t('there was an error'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
