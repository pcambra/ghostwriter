<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Data\ScriptImporter;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\mcp_server\Attribute\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Saves a script for reuse by script_id.
 */
#[Tool(
  id: 'script_import',
  label: new TranslatableMarkup('Import script'),
  description: new TranslatableMarkup('Save an official script-tool JSON array so night_order, jinxes and setup can take its script_id. Every character must be an official id. Saving under an existing id replaces that script. Admin only.'),
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'script' => GhostwriterToolBase::SET_PROPERTIES['script'],
      'id' => [
        'type' => 'string',
        'description' => 'Machine name to save under. Defaults to the _meta name, lowercased with underscores.',
      ],
    ],
    'required' => ['script'],
  ],
  readOnly: FALSE,
  destructive: TRUE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
final class ScriptImport extends GhostwriterToolBase {

  /**
   * The script importer.
   */
  private ScriptImporter $importer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->importer = $container->get('ghostwriter.script_importer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer ghostwriter');
  }

  /**
   * {@inheritdoc}
   */
  protected function run(array $arguments): array {
    return $this->importer->import($arguments['script'], $arguments['id'] ?? NULL);
  }

}
