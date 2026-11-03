<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server\ResourceTemplateProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\ghostwriter\Data\DataMissingException;
use Drupal\mcp_server\Attribute\ResourceTemplateProvider;
use Drupal\mcp_server\Plugin\ResourceTemplateProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Exception\ResourceReadException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Character, edition and saved script resources.
 */
#[ResourceTemplateProvider(
  id: 'ghostwriter_resources',
  label: new TranslatableMarkup('Ghostwriter characters and editions'),
  description: new TranslatableMarkup('Official Blood on the Clocktower character and edition data.'),
)]
final class GhostwriterResources extends ResourceTemplateProviderBase {

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
  public function getUriTemplate(): string {
    return 'ghostwriter://character/{id}';
  }

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      [
        'uri' => 'ghostwriter://character/{id}',
        'name' => 'character',
        'description' => 'One character: same JSON as the character tool.',
      ],
      [
        'uri' => 'ghostwriter://edition/{id}',
        'name' => 'edition',
        'description' => 'One edition with full character objects grouped by team.',
      ],
      [
        'uri' => 'ghostwriter://script/{id}',
        'name' => 'script',
        'description' => 'One saved script with its _meta and full character objects in script order.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    if (!preg_match('#^ghostwriter://(character|edition|script)/([^/]+)$#', $uri, $matches)) {
      return NULL;
    }
    $id = urldecode($matches[2]);
    try {
      $data = match ($matches[1]) {
        'character' => $this->characters->character($id),
        'edition' => $this->characters->edition($id),
        'script' => $this->characters->script($id),
      };
      if (isset($data['error'])) {
        return NULL;
      }
      $tags = ['ghostwriter_data'];
      if ($matches[1] === 'edition') {
        $data['characters'] = array_map(
          fn(array $members): array => array_map(fn(array $member): array => $this->characters->character($member['id']), $members),
          $data['characters'],
        );
      }
      if ($matches[1] === 'script') {
        $data['characters'] = array_map($this->characters->character(...), $data['characters']);
        $tags[] = 'config:ghostwriter_script_list';
      }
    }
    catch (DataMissingException $e) {
      throw new ResourceReadException($e->getMessage(), 0, $e);
    }
    return CacheableResourceContent::fromArray([
      'uri' => $uri,
      'mimeType' => 'application/json',
      'text' => json_encode(['data' => $data, 'meta' => $this->characters->meta()], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ], (new CacheableMetadata())->addCacheTags($tags));
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access mcp server');
  }

}
