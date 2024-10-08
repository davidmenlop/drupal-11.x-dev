<?php

namespace Drupal\Core\Field\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a FieldWidget annotation object.
 *
 * Widgets handle how fields are displayed in edit forms.
 *
 * Additional annotation keys for widgets can be defined in
 * hook_field_widget_info_alter().
 *
 * @Annotation
 *
 * @see \Drupal\Core\Field\WidgetPluginManager
 * @see \Drupal\Core\Field\WidgetInterface
 *
 * @ingroup field_widget
 */
class FieldWidget extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the widget type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the widget type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The name of the widget class.
   *
   * This is not provided manually, it will be added by the discovery mechanism.
   *
   * @var string
   */
  public $class;

  /**
   * An array of field types the widget supports.
   *
   * @var array
   */
  public $field_types = [];

  /**
   * Does the field widget handles multiple values at once.
   *
   * @var bool
   */
  public $multiple_values = FALSE;

  /**
   * An integer to determine weight of this widget relative to other widgets.
   *
   * Other widgets are in the Field UI when selecting a widget for a given
   * field.
   *
   * This property is optional and it does not need to be declared.
   *
   * @var int
   */
  public $weight = NULL;

}
