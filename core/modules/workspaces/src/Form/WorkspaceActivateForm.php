<?php

namespace Drupal\workspaces\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\workspaces\WorkspaceAccessException;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle activation of a workspace on administrative pages.
 */
class WorkspaceActivateForm extends EntityConfirmFormBase implements WorkspaceSafeFormInterface {

  /**
   * The workspace entity.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $entity;

  /**
   * The workspace replication manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new WorkspaceActivateForm.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, MessengerInterface $messenger) {
    $this->workspaceManager = $workspace_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Would you like to activate the %workspace workspace?', ['%workspace' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Activate the %workspace workspace.', ['%workspace' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Content entity forms do not use the parent's #after_build callback.
    unset($form['#after_build']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['cancel']['#attributes']['class'][] = 'dialog-cancel';
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->workspaceManager->setActiveWorkspace($this->entity);
      $this->messenger->addMessage($this->t('%workspace_label is now the active workspace.', ['%workspace_label' => $this->entity->label()]));
    }
    catch (WorkspaceAccessException) {
      $this->messenger->addError($this->t('You do not have access to activate the %workspace_label workspace.', ['%workspace_label' => $this->entity->label()]));
    }

    // Redirect to the workspace manage page by default.
    if (!$this->getRequest()->query->has('destination')) {
      $form_state->setRedirectUrl($this->entity->toUrl());
    }
  }

  /**
   * Checks access for the workspace activate form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(RouteMatchInterface $route_match) {
    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $route_match->getParameter('workspace');
    $active_workspace = $this->workspaceManager->getActiveWorkspace();

    $access = AccessResult::allowedIf(!$active_workspace || ($active_workspace && $active_workspace->id() != $workspace->id()))
      ->addCacheableDependency($workspace);

    return $access;
  }

}
