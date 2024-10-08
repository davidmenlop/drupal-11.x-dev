<?php

declare(strict_types=1);

namespace Drupal\search_embedded_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for search_embedded_form form.
 *
 * @internal
 */
class SearchEmbeddedForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_embedded_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $count = \Drupal::state()->get('search_embedded_form.submit_count', 0);

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#maxlength' => 255,
      '#default_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Times form has been submitted: %count', ['%count' => $count]),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send away'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $state = \Drupal::state();
    $submit_count = $state->get('search_embedded_form.submit_count', 0);
    $state->set('search_embedded_form.submit_count', $submit_count + 1);
    $this->messenger()->addStatus($this->t('Test form was submitted'));
  }

}
