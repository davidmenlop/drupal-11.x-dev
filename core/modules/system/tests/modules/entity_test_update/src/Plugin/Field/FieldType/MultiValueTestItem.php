<?php

declare(strict_types=1);

namespace Drupal\entity_test_update\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'multi_value_test' field type.
 */
#[FieldType(
  id: "multi_value_test",
  label: new TranslatableMarkup("Multiple values test"),
  description: new TranslatableMarkup("Another dummy field type."),
)]
class MultiValueTestItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value1'] = DataDefinition::create('string')
      ->setLabel(t('First value'));

    $properties['value2'] = DataDefinition::create('string')
      ->setLabel(t('Second value'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value1' => [
          'type' => 'varchar',
          'length' => 64,
        ],
        'value2' => [
          'type' => 'varchar',
          'length' => 64,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $item = $this->getValue();
    return empty($item['value1']) && empty($item['value2']);
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'value1';
  }

}
