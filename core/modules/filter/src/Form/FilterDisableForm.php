<?php

namespace Drupal\filter\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the filter format disable form.
 *
 * @internal
 */
class FilterDisableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable the text format %format?', ['%format' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('filter.admin_overview');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t(
      'Any content saved with the %format text format will not be displayed on the site until it is resaved with an enabled text format.',
      ['%format' => $this->entity->label()],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->disable()->save();
    $this->messenger()->addStatus($this->t('Disabled text format %format.', ['%format' => $this->entity->label()]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
