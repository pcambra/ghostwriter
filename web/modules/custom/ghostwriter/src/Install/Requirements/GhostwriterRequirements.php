<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Site\Settings;

/**
 * Blocks install until a private file path is configured.
 */
final class GhostwriterRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    if (Settings::get('file_private_path')) {
      return [];
    }
    return [
      'ghostwriter_private_path' => [
        'title' => t('Ghostwriter private files'),
        'description' => t('Ghostwriter stores the official data under private://. Add this line to settings.php: <code>@line</code>', [
          '@line' => "\$settings['file_private_path'] = '../private';",
        ]),
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

}
