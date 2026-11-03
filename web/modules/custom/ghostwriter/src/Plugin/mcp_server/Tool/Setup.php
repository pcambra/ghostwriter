<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;

/**
 * Official setup counts and the characters that modify them.
 */
#[Tool(
  id: 'setup',
  label: new TranslatableMarkup('Setup'),
  description: new TranslatableMarkup('Get the official base counts of Townsfolk, Outsiders, Minions and Demons for a player count. Pass characters or a script to also list the ones whose ability changes setup, with their official ability text. Counts are never adjusted: the ability text says how.'),
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'players' => [
        'type' => 'integer',
        'minimum' => 5,
        'description' => 'Number of players, not counting Travellers. 15 or more use the 15+ row.',
      ],
    ] + GhostwriterToolBase::SET_PROPERTIES,
    'required' => ['players'],
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class Setup extends GhostwriterToolBase {

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    $set = array_intersect_key($arguments, self::SET_PROPERTIES) ? $this->characters->resolveSet($arguments) : ['ids' => []];
    return isset($set['error']) ? $set : $this->characters->setup($arguments['players'], $set['ids']);
  }

}
