<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Action;

use Drupal\Core\Action\ActionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Action;
use Drupal\user\RoleInterface;

/**
 * Tests action plugins.
 *
 * @group Action
 */
class ActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'field', 'user', 'action_test'];

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->actionManager = $this->container->get('plugin.manager.action');
    $this->installEntitySchema('user');
    $this->installConfig('user');
  }

  /**
   * Tests the functionality of test actions.
   */
  public function testOperations(): void {
    // Test that actions can be discovered.
    $definitions = $this->actionManager->getDefinitions();
    // Verify that the action definitions are found.
    $this->assertGreaterThan(1, count($definitions));
    $this->assertNotEmpty($definitions['action_test_no_type'], 'The test action is among the definitions found.');

    $definition = $this->actionManager->getDefinition('action_test_no_type');
    $this->assertNotEmpty($definition, 'The test action definition is found.');

    $definitions = $this->actionManager->getDefinitionsByType('user');
    $this->assertArrayNotHasKey('action_test_no_type', $definitions, 'An action with no type is not found.');

    // Create an instance of the 'save entity' action.
    $action = $this->actionManager->createInstance('action_test_save_entity');
    $this->assertInstanceOf(ActionInterface::class, $action);

    // Create a new unsaved user.
    $name = $this->randomMachineName();
    $user_storage = $this->container->get('entity_type.manager')->getStorage('user');
    $account = $user_storage->create(['name' => $name, 'bundle' => 'user']);
    $loaded_accounts = $user_storage->loadMultiple();
    $this->assertCount(0, $loaded_accounts);

    // Execute the 'save entity' action.
    $action->execute($account);
    $loaded_accounts = $user_storage->loadMultiple();
    $this->assertCount(1, $loaded_accounts);
    $account = reset($loaded_accounts);
    $this->assertEquals($name, $account->label());
  }

  /**
   * Tests the dependency calculation of actions.
   */
  public function testDependencies(): void {
    // Create a new action that depends on a user role.
    $action = Action::create([
      'id' => 'user_add_role_action.' . RoleInterface::ANONYMOUS_ID,
      'type' => 'user',
      'label' => 'Add the anonymous role to the selected users',
      'configuration' => [
        'rid' => RoleInterface::ANONYMOUS_ID,
      ],
      'plugin' => 'user_add_role_action',
    ]);
    $action->save();

    $expected = [
      'config' => [
        'user.role.' . RoleInterface::ANONYMOUS_ID,
      ],
      'module' => [
        'user',
      ],
    ];
    $this->assertSame($expected, $action->calculateDependencies()->getDependencies());
  }

  /**
   * Tests no type specified action.
   */
  public function testNoTypeAction(): void {
    // Create an action config entity using the action_test_no_type plugin.
    $action = Action::create([
      'id' => 'action_test_no_type_action',
      'label' => 'Test Action with No Type',
      'plugin' => 'action_test_no_type',
    ]);
    $action->save();

    // Reload the action to ensure it's saved correctly.
    $action = Action::load('action_test_no_type_action');

    // Assert that the action was saved and loaded correctly.
    $this->assertNotNull($action, 'The action config entity was saved and loaded correctly.');
    $this->assertSame('action_test_no_type_action', $action->id(), 'The action ID is correct.');
    $this->assertSame('Test Action with No Type', $action->label(), 'The action label is correct.');
    $this->assertNull($action->getType(), 'The action type is correctly set to NULL.');
  }

}
