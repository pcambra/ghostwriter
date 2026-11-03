<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Data;

/**
 * Thrown when the official data files have not been synced yet.
 */
final class DataMissingException extends \RuntimeException {

  public function __construct() {
    parent::__construct('No data synced yet. Run drush ghostwriter:sync or the refresh_data tool.');
  }

}
