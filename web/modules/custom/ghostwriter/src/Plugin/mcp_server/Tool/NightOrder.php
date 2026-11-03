<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;

/**
 * Storyteller night order for a set of characters.
 */
#[Tool(
  id: 'night_order',
  label: new TranslatableMarkup('Night order'),
  description: new TranslatableMarkup('Compute the Storyteller night order for a set of characters, first night or other nights, with the official reminder text for each step, including Dusk, Minion info, Demon info and Dawn. Pass character ids, or paste an official script JSON.'),
  inputSchema: [
    'type' => 'object',
    'properties' => GhostwriterToolBase::SET_PROPERTIES + [
      'night' => [
        'type' => 'string',
        'enum' => ['first', 'other'],
      ],
    ],
    'required' => ['night'],
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class NightOrder extends GhostwriterToolBase {

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    $set = $this->characters->resolveSet($arguments);
    return isset($set['error']) ? $set : $this->characters->nightOrder($set['ids'], $arguments['night'], $set['meta']);
  }

}
