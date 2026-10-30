<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;

/**
 * One character's official data.
 */
#[Tool(
  id: 'character',
  label: new TranslatableMarkup('Character'),
  description: new TranslatableMarkup('Look up one Blood on the Clocktower character by id or name: team, edition, ability text, setup flag, reminder tokens, night reminder text, and its jinxes. Official data.'),
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'id' => [
        'type' => 'string',
        'description' => 'Character id or name, e.g. "chambermaid" or "Scarlet Woman".',
      ],
    ],
    'required' => ['id'],
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class Character extends GhostwriterToolBase {

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    return $this->characters->character($arguments['id']);
  }

}
