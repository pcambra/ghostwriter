<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;

/**
 * Characters of an official edition.
 */
#[Tool(
  id: 'edition',
  label: new TranslatableMarkup('Edition'),
  description: new TranslatableMarkup('List every character in an official edition (tb = Trouble Brewing, bmr = Bad Moon Rising, snv = Sects & Violets, and the others present in the data) grouped by team. Use this to get the ids for night_order or jinxes.'),
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'id' => [
        'type' => 'string',
        'description' => 'Edition id, e.g. "tb". An unknown id returns the list of known ids.',
      ],
    ],
    'required' => ['id'],
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class Edition extends GhostwriterToolBase {

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    return $this->characters->edition($arguments['id']);
  }

}
