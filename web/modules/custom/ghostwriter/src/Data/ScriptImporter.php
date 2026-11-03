<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Data;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Saves official script JSON as ghostwriter_script config entities.
 */
final class ScriptImporter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CharacterRepository $characters,
    #[Autowire(service: 'transliteration')]
    private readonly TransliterationInterface $transliteration,
  ) {}

  /**
   * Saves a script under $id, else its _meta name; the same id overwrites.
   *
   * @phpstan-impure
   */
  public function import(mixed $script, ?string $id = NULL): array {
    if (!is_array($script) || !array_is_list($script)) {
      return ['error' => ['code' => 'invalid_script', 'message' => 'A script is a JSON array.']];
    }
    $set = $this->characters->resolveSet(['script' => $script]);
    if (isset($set['error'])) {
      return $set;
    }
    $name = is_string($set['meta']['name'] ?? NULL) ? $set['meta']['name'] : NULL;
    $id = trim(preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower($this->transliteration->transliterate($id ?? $name ?? '', 'en', '_'))), '_');
    if ($id === '') {
      return ['error' => ['code' => 'invalid_script', 'message' => 'Pass an id, or a _meta entry with a name.']];
    }

    $storage = $this->entityTypeManager->getStorage('ghostwriter_script');
    $entity = $storage->load($id) ?? $storage->create(['id' => $id]);
    $status = $entity->isNew() ? 'created' : 'updated';
    $entity
      ->set('label', $name ?? $id)
      ->set('author', is_string($set['meta']['author'] ?? NULL) ? $set['meta']['author'] : '')
      ->set('characters', $set['ids'])
      ->set('meta', $set['meta'])
      ->save();
    return ['id' => $id, 'label' => $entity->label(), 'status' => $status, 'characters' => $set['ids']];
  }

}
