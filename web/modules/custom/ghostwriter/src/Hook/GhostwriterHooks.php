<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Hook;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\ghostwriter\Install\Requirements\GhostwriterRequirements;

/**
 * Hook implementations for Ghostwriter.
 */
final class GhostwriterHooks {

  use StringTranslationTrait;

  public const INSTRUCTIONS = <<<'TEXT'
    Ghostwriter serves the official Blood on the Clocktower data that The Pandemonium Institute publishes for toolmakers. Tools return that data verbatim with its source and never rule on character interactions. Use edition to get character ids, then night_order, jinxes or setup. Those three also take the script_id of a script saved with script_import.
    Blood on the Clocktower is a trademark of Steven Medway and The Pandemonium Institute.
    Community Created Content: not affiliated with or endorsed by The Pandemonium Institute.
    TEXT;

  public const OAUTH_SCOPE = 'mcp';

  public function __construct(
    private readonly CharacterRepository $characters,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = GhostwriterRequirements::getRequirements();
    $status = $this->characters->status();
    $requirements['ghostwriter_data'] = [
      'title' => $this->t('Ghostwriter data'),
      'value' => $status['fetched_at'] ?? $this->t('Never fetched'),
      'severity' => RequirementSeverity::OK,
    ];
    $url = Url::fromRoute('ghostwriter.refresh')->toString();
    if (!$status['fetched_at']) {
      $requirements['ghostwriter_data']['severity'] = RequirementSeverity::Error;
      $requirements['ghostwriter_data']['description'] = $this->t('No official data yet. <a href=":url">Refresh it</a> or run <code>drush ghostwriter:sync</code>.', [':url' => $url]);
    }
    elseif ($status['stale']) {
      $requirements['ghostwriter_data']['severity'] = RequirementSeverity::Warning;
      $requirements['ghostwriter_data']['description'] = $this->t('The data is older than the configured limit. <a href=":url">Refresh it</a> or run <code>drush ghostwriter:sync</code>.', [':url' => $url]);
    }
    return $requirements;
  }

  /**
   * Implements hook_mcp_server_instructions_alter().
   *
   * @param-out string $instructions
   */
  #[Hook('mcp_server_instructions_alter')]
  public function mcpServerInstructionsAlter(?string &$instructions): void {
    $instructions = trim($instructions . "\n\n" . self::INSTRUCTIONS);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for consumer.
   *
   * MCP clients register themselves and ask for no scope, and a token without
   * a scope grants no permission, so new clients default to the mcp scope.
   * Simple OAuth adds the scope field; Consumers saves its default consumer
   * before that field exists.
   */
  #[Hook('consumer_presave')]
  public function consumerPresave(ConsumerInterface $consumer): void {
    if ($consumer->isNew() && $consumer->hasField('authorization_code_scopes') && $consumer->get('authorization_code_scopes')->isEmpty()) {
      $consumer->set('authorization_code_scopes', self::OAUTH_SCOPE);
    }
  }

}
