<?php

namespace Drupal\Core\Field\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a FieldFormatter annotation object.
 *
 * Formatters handle the display of field values. They are typically
 * instantiated and invoked by an EntityDisplay object.
 *
 * Additional annotation keys for formatters can be defined in
 * hook_field_formatter_info_alter().
 *
 * @Annotation
 *
 * @see \Drupal\Core\Field\FormatterPluginManager
 * @see \Drupal\Core\Field\FormatterInterface
 *
 * @ingroup field_formatter
 */
class FieldFormatter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the formatter type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the formatter type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The name of the field formatter class.
   *
   * This is not provided manually, it will be added by the discovery mechanism.
   *
   * @var string
   */
  public $class;

  /**
   * An array of field types the formatter supports.
   *
   * @var array
   */
  public $field_types = [];

  /**
   * An integer to determine the weight of this formatter.
   *
   * Weight is relative to other formatter in the Field UI when selecting a
   * formatter for a given field instance.
   *
   * This property is optional and it does not need to be declared.
   *
   * @var int
   */
  public $weight = NULL;

}
