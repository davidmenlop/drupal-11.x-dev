<?php

namespace Drupal\image\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an image effect annotation object.
 *
 * Plugin Namespace: Plugin\ImageEffect
 *
 * For a working example, see
 * \Drupal\image\Plugin\ImageEffect\ResizeImageEffect
 *
 * @see hook_image_effect_info_alter()
 * @see \Drupal\image\ConfigurableImageEffectInterface
 * @see \Drupal\image\ConfigurableImageEffectBase
 * @see \Drupal\image\ImageEffectInterface
 * @see \Drupal\image\ImageEffectBase
 * @see \Drupal\image\ImageEffectManager
 * @see \Drupal\Core\ImageToolkit\Annotation\ImageToolkitOperation
 * @see plugin_api
 *
 * @Annotation
 */
class ImageEffect extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the image effect.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the image effect.
   *
   * This property is optional and it does not need to be declared.
   *
   * This will be shown when adding or configuring this image effect.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

}
