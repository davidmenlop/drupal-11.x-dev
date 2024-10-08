<?php

declare(strict_types=1);

namespace Drupal\image_module_test\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the Dummy image formatter.
 */
#[FieldFormatter(
  id: 'dummy_image_formatter',
  label: new TranslatableMarkup('Dummy image'),
  field_types: [
    'image',
  ],
)]
class DummyImageFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return [
      ['#markup' => 'Dummy'],
    ];
  }

}
