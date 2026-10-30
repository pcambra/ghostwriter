<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;

/**
 * Word search over names, abilities and reminder text.
 */
#[Tool(
  id: 'search_characters',
  label: new TranslatableMarkup('Search characters'),
  description: new TranslatableMarkup('Full-text search over character names, ability text and reminder text. Optional team and edition filters. Returns matching ids with the matched snippet.'),
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'query' => [
        'type' => 'string',
        'minLength' => 1,
        'description' => 'Whole words to find; every word must appear, e.g. "drunk" or "learn demon".',
      ],
      'team' => [
        'type' => 'string',
        'enum' => CharacterRepository::TEAMS,
      ],
      'edition' => [
        'type' => 'string',
        'description' => 'Edition id, e.g. "bmr".',
      ],
      'limit' => [
        'type' => 'integer',
        'minimum' => 1,
        'maximum' => 50,
        'default' => 20,
      ],
    ],
    'required' => ['query'],
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class SearchCharacters extends GhostwriterToolBase {

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    return $this->characters->search($arguments['query'], $arguments['team'] ?? NULL, $arguments['edition'] ?? NULL, $arguments['limit'] ?? 20);
  }

}
