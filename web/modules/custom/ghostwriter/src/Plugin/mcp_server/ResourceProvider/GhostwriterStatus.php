<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\ResourceProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\mcp_server\Attribute\ResourceProvider;
use Drupal\mcp_server\Plugin\ResourceProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync status resource; the SDK rejects templates without a placeholder.
 */
#[ResourceProvider(
  id: 'ghostwriter_status',
  label: new TranslatableMarkup('Ghostwriter data status'),
  description: new TranslatableMarkup('When the official data was last fetched and whether it is stale.'),
)]
final class GhostwriterStatus extends ResourceProviderBase {

  public const URI = 'ghostwriter://data/status';

  /**
   * The character repository.
   */
  private CharacterRepository $characters;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->characters = $container->get('ghostwriter.characters');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      [
        'uri' => self::URI,
        'name' => 'status',
        'description' => 'fetched_at, sha256 per file, and stale flag.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    if ($uri !== self::URI) {
      return NULL;
    }
    return CacheableResourceContent::fromArray([
      'uri' => $uri,
      'mimeType' => 'application/json',
      'text' => json_encode($this->characters->status(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ], (new CacheableMetadata())->setCacheMaxAge(0));
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access mcp server');
  }

}
