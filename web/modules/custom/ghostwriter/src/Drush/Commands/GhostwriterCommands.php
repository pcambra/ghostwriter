<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Drush\Commands;

use Drupal\ghostwriter\Data\ScriptImporter;
use Drupal\ghostwriter\Sync\DataSync;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Ghostwriter.
 */
final class GhostwriterCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly DataSync $dataSync,
    private readonly ScriptImporter $scriptImporter,
  ) {
    parent::__construct();
  }

  /**
   * Downloads the official data files from release.botc.app.
   */
  #[CLI\Command(name: 'ghostwriter:sync')]
  #[CLI\Option(name: 'force', description: 'Ignore ETags and download every file.')]
  #[CLI\Usage(name: 'drush ghostwriter:sync --force', description: 'Download all three files even when unchanged.')]
  public function sync(array $options = ['force' => FALSE]): int {
    $result = $this->dataSync->sync((bool) $options['force']);
    if (isset($result['error'])) {
      $this->logger()->error($result['error']['message']);
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('Changed: @changed. Unchanged: @unchanged. Fetched at @fetched_at.', [
      '@changed' => implode(', ', $result['changed']) ?: 'none',
      '@unchanged' => implode(', ', $result['unchanged']) ?: 'none',
      '@fetched_at' => $result['fetched_at'],
    ]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Saves an official script JSON file as a script.
   */
  #[CLI\Command(name: 'ghostwriter:script:import')]
  #[CLI\Argument(name: 'path', description: 'Path to a script JSON file from the official script tool.')]
  #[CLI\Option(name: 'id', description: 'Machine name to save under. Defaults to the _meta name.')]
  #[CLI\Usage(name: 'drush ghostwriter:script:import scripts/gloom.json --id=gloom', description: 'Save the script as gloom, replacing any script with that id.')]
  public function scriptImport(string $path, array $options = ['id' => NULL]): int {
    if (!is_readable($path)) {
      $this->logger()->error(dt('Cannot read @path.', ['@path' => $path]));
      return self::EXIT_FAILURE;
    }
    $result = $this->scriptImporter->import(json_decode(file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR), $options['id']);
    if (isset($result['error'])) {
      $this->logger()->error(json_encode($result['error'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('Script @id @status with @count characters.', [
      '@id' => $result['id'],
      '@status' => $result['status'],
      '@count' => count($result['characters']),
    ]));
    return self::EXIT_SUCCESS;
  }

}
