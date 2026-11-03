<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A saved script: official character ids plus its raw _meta entry.
 */
#[ConfigEntityType(
  id: 'ghostwriter_script',
  label: new TranslatableMarkup('Script'),
  label_collection: new TranslatableMarkup('Scripts'),
  label_singular: new TranslatableMarkup('script'),
  label_plural: new TranslatableMarkup('scripts'),
  config_prefix: 'script',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  admin_permission: 'administer ghostwriter',
  config_export: [
    'id',
    'label',
    'author',
    'characters',
    'meta',
  ],
)]
class Script extends ConfigEntityBase {

  /**
   * The machine name.
   *
   * @var string
   */
  protected $id;

  /**
   * The script name.
   *
   * @var string
   */
  protected $label;

  /**
   * The script author.
   *
   * @var string
   */
  protected $author = '';

  /**
   * Official character ids, in script order.
   *
   * @var string[]
   */
  protected $characters = [];

  /**
   * The script's _meta entry as imported.
   *
   * @var array
   */
  protected $meta = [];

}
