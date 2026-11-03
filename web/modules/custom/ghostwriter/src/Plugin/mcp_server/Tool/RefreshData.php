<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Plugin\mcp_server\GhostwriterToolBase;
use Drupal\ghostwriter\Sync\DataSync;
use Drupal\mcp_server\Attribute\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Re-downloads the official data files.
 */
#[Tool(
  id: 'refresh_data',
  label: new TranslatableMarkup('Refresh data'),
  description: new TranslatableMarkup('Re-download the official data files from release.botc.app. Admin only.'),
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'force' => [
        'type' => 'boolean',
        'description' => 'Ignore ETags and download every file.',
      ],
    ],
  ],
  readOnly: FALSE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: TRUE,
)]
final class RefreshData extends GhostwriterToolBase {

  /**
   * The data sync service.
   */
  private DataSync $sync;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sync = $container->get('ghostwriter.sync');
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
    return $this->sync->sync($arguments['force'] ?? FALSE);
  }

}
