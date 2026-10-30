<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Plugin\mcp_server;

use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\ghostwriter\Data\DataMissingException;
use Drupal\mcp_server\Plugin\ToolPluginBase;
use Mcp\Server\ClientGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enabled by default; wraps results in the provenance envelope.
 */
abstract class GhostwriterToolBase extends ToolPluginBase {

  public const SET_PROPERTIES = [
    'characters' => [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'description' => 'Character ids or names, e.g. ["washerwoman", "Scarlet Woman"]. Use only one of characters, script or script_id.',
    ],
    'script' => [
      'type' => 'array',
      'description' => 'An official script-tool JSON array: {"id": ...} objects or bare id strings. An {"id": "_meta"} entry may carry firstNight/otherNight order arrays. Use only one of characters, script or script_id.',
    ],
    'script_id' => [
      'type' => 'string',
      'description' => 'Machine name of a script saved with script_import. Use only one of characters, script or script_id.',
    ],
  ];

  /**
   * The character repository.
   */
  protected CharacterRepository $characters;

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
  protected function defaultConfiguration(): array {
    return ['enabled' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments, ClientGateway $gateway): mixed {
    try {
      $result = $this->run($arguments);
    }
    catch (DataMissingException $e) {
      return ['error' => ['code' => 'data_missing', 'hint' => $e->getMessage()]];
    }
    return isset($result['error']) ? $result : ['data' => $result, 'meta' => $this->characters->meta()];
  }

  /**
   * Returns the data part of the response, or an error array.
   */
  abstract protected function run(array $arguments): array;

}
