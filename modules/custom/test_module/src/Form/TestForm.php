<?php

namespace Drupal\test_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;

class TestForm extends FormBase {

  public function getFormId() {
    return 'test_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['document_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Document Type'),
      '#options' => $this->getTaxonomyOptions('document_type'),
      '#required' => TRUE,
    ];

    $form['document_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document Number'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
    ];

    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $this->getTaxonomyOptions('country'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  private function getTaxonomyOptions($vocabulary_id) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary_id);
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }
    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = [
      'first_name' => $form_state->getValue('first_name'),
      'last_name' => $form_state->getValue('last_name'),
      'document_type' => $form_state->getValue('document_type'),
      'document_number' => $form_state->getValue('document_number'),
      'email' => $form_state->getValue('email'),
      'phone' => $form_state->getValue('phone'),
      'country' => $form_state->getValue('country'),
    ];
    \Drupal::database()->insert('test_form_data')->fields($data)->execute();
    \Drupal::messenger()->addStatus($this->t('Form submitted successfully.'));
  }
}
