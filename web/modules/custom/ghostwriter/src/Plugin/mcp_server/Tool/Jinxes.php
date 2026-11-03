<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;

/**
 * Official jinxes live between a set of characters.
 */
#[Tool(
  id: 'jinxes',
  label: new TranslatableMarkup('Jinxes'),
  description: new TranslatableMarkup('List the official jinxes that are live between a given set of characters (both characters must be in the set). Returns the official jinx text verbatim.'),
  inputSchema: [
    'type' => 'object',
    'properties' => GhostwriterToolBase::SET_PROPERTIES,
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class Jinxes extends GhostwriterToolBase {

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    $set = $this->characters->resolveSet($arguments);
    return isset($set['error']) ? $set : $this->characters->jinxesBetween($set['ids']);
  }

}
