<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A saved script: official character ids plus its raw _meta entry.
 *
 * Content, not config: scripts are saved on the live site, and a config
 * import would delete any that are missing from the sync directory.
 */
#[ContentEntityType(
  id: 'ghostwriter_script',
  label: new TranslatableMarkup('Script'),
  label_collection: new TranslatableMarkup('Scripts'),
  label_singular: new TranslatableMarkup('script'),
  label_plural: new TranslatableMarkup('scripts'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'label',
  ],
  base_table: 'ghostwriter_script',
  admin_permission: 'administer ghostwriter',
)]
class Script extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Machine name'))
      ->setReadOnly(TRUE)
      ->setSetting('is_ascii', TRUE);
    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE);
    $fields['author'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Author'));
    $fields['characters'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Character ids'))
      ->setDescription(new TranslatableMarkup('Official character ids, in script order.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $fields['meta'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Script _meta entry'))
      ->setDescription(new TranslatableMarkup('The _meta entry as imported.'));
    return $fields;
  }

}
