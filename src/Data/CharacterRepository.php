<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Data;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ghostwriter\Entity\Script;
use Drupal\ghostwriter\Sync\DataSync;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Looks things up in the official data and saved scripts. Never rules.
 */
final class CharacterRepository {

  public const TEAMS = ['townsfolk', 'outsider', 'minion', 'demon', 'traveller', 'fabled', 'loric'];

  public const MARKERS = [
    'dusk' => 'Dusk',
    'minioninfo' => 'Minion info',
    'demoninfo' => 'Demon info',
    'dawn' => 'Dawn',
  ];

  public const SETUP = [
    5 => ['townsfolk' => 3, 'outsider' => 0, 'minion' => 1, 'demon' => 1],
    6 => ['townsfolk' => 3, 'outsider' => 1, 'minion' => 1, 'demon' => 1],
    7 => ['townsfolk' => 5, 'outsider' => 0, 'minion' => 1, 'demon' => 1],
    8 => ['townsfolk' => 5, 'outsider' => 1, 'minion' => 1, 'demon' => 1],
    9 => ['townsfolk' => 5, 'outsider' => 2, 'minion' => 1, 'demon' => 1],
    10 => ['townsfolk' => 7, 'outsider' => 0, 'minion' => 2, 'demon' => 1],
    11 => ['townsfolk' => 7, 'outsider' => 1, 'minion' => 2, 'demon' => 1],
    12 => ['townsfolk' => 7, 'outsider' => 2, 'minion' => 2, 'demon' => 1],
    13 => ['townsfolk' => 9, 'outsider' => 0, 'minion' => 3, 'demon' => 1],
    14 => ['townsfolk' => 9, 'outsider' => 1, 'minion' => 3, 'demon' => 1],
    15 => ['townsfolk' => 9, 'outsider' => 2, 'minion' => 3, 'demon' => 1],
  ];

  public const NOTICE = "Official TPI data, returned verbatim. Interactions not covered by a jinx are the Storyteller's call.";

  public function __construct(
    #[Autowire(service: 'cache.default')]
    private readonly CacheBackendInterface $cache,
    private readonly StateInterface $state,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Turns "Pit-Hag" or "Scarlet Woman" into the official id format.
   */
  public static function normalize(string $id): string {
    return str_replace([' ', '-', "'", '’', '_'], '', mb_strtolower(trim($id)));
  }

  /**
   * Provenance block attached to every tool response.
   */
  public function meta(): array {
    return [
      'source' => $this->state->get('ghostwriter.source'),
      'fetched_at' => $this->status()['fetched_at'],
      'roles_sha256' => $this->state->get('ghostwriter.sha256.roles.json'),
      'notice' => self::NOTICE,
    ];
  }

  /**
   * Sync status from State only, so it works before the first sync.
   */
  public function status(): array {
    $fetched_at = $this->state->get('ghostwriter.fetched_at');
    $sha256s = [];
    foreach (DataSync::FILES as $file) {
      $sha256s[$file] = $this->state->get("ghostwriter.sha256.$file");
    }
    $stale_after = $this->configFactory->get('ghostwriter.settings')->get('stale_after_days') * 86400;
    return [
      'fetched_at' => $fetched_at ? gmdate('c', $fetched_at) : NULL,
      'sha256s' => $sha256s,
      'stale' => !$fetched_at || $this->time->getCurrentTime() - $fetched_at > $stale_after,
    ];
  }

  /**
   * One character with its night info and jinxes.
   */
  public function character(string $id): array {
    $model = $this->model();
    $id = self::normalize($id);
    if (!isset($model['roles'][$id])) {
      return $this->unknown([$id]);
    }
    $role = $model['roles'][$id];
    $jinxes = [];
    foreach ($model['jinxes'] as $jinx) {
      if ($jinx['a'] === $id || $jinx['b'] === $id) {
        $jinxes[] = ['with' => $jinx['a'] === $id ? $jinx['b'] : $jinx['a'], 'reason' => $jinx['reason']];
      }
    }
    return [
      'id' => $id,
      'name' => $role['name'],
      'team' => $role['team'],
      'edition' => $role['edition'],
      'ability' => $role['ability'],
      'setup' => $role['setup'] ?? FALSE,
      'reminders' => $role['reminders'] ?? [],
      'remindersGlobal' => $role['remindersGlobal'] ?? [],
      'firstNight' => in_array($id, $model['night']['first'], TRUE),
      'firstNightReminder' => $role['firstNightReminder'] ?? '',
      'otherNight' => in_array($id, $model['night']['other'], TRUE),
      'otherNightReminder' => $role['otherNightReminder'] ?? '',
      'flavor' => $role['flavor'] ?? NULL,
      'jinxes' => $jinxes,
    ];
  }

  /**
   * Characters of one edition grouped by team.
   */
  public function edition(string $id): array {
    $editions = $this->model()['editions'];
    $id = self::normalize($id);
    if (!isset($editions[$id])) {
      return [
        'error' => [
          'code' => 'unknown_edition',
          'ids' => [$id],
          'hint' => 'Known editions: ' . implode(', ', array_keys($editions)),
        ],
      ];
    }
    return ['id' => $id, 'characters' => array_merge(array_fill_keys(self::TEAMS, []), $editions[$id])];
  }

  /**
   * One saved script as imported.
   */
  public function script(string $id): array {
    $storage = $this->entityTypeManager->getStorage('ghostwriter_script');
    $script = $storage->load($id);
    if (!$script instanceof Script) {
      $known = array_keys($storage->loadMultiple());
      return [
        'error' => [
          'code' => 'unknown_script',
          'ids' => [$id],
          'hint' => $known ? 'Known scripts: ' . implode(', ', $known) : 'No scripts saved yet. Import one with script_import.',
        ],
      ];
    }
    return [
      'id' => $id,
      'label' => $script->label(),
      'author' => $script->get('author'),
      'characters' => $script->get('characters'),
      'meta' => $script->get('meta'),
    ];
  }

  /**
   * Resolves the characters/script/script_id input into known ids plus _meta.
   */
  public function resolveSet(array $arguments): array {
    $given = array_filter(['characters', 'script', 'script_id'], fn(string $key): bool => isset($arguments[$key]));
    if (count($given) !== 1) {
      return [
        'error' => [
          'code' => 'invalid_script',
          'message' => 'Pass exactly one of characters, script or script_id.',
        ],
      ];
    }
    $ids = $arguments['characters'] ?? [];
    $meta = [];
    if (isset($arguments['script_id'])) {
      $script = $this->script($arguments['script_id']);
      if (isset($script['error'])) {
        return $script;
      }
      [$ids, $meta] = [$script['characters'], $script['meta']];
    }
    foreach ($arguments['script'] ?? [] as $entry) {
      $id = is_array($entry) ? ($entry['id'] ?? NULL) : $entry;
      if (!is_string($id)) {
        return [
          'error' => [
            'code' => 'invalid_script',
            'message' => 'Script entries must be id strings or objects with an id.',
          ],
        ];
      }
      if ($id === '_meta') {
        $meta = $entry;
      }
      else {
        $ids[] = $id;
      }
    }
    $ids = array_values(array_unique(array_map(self::normalize(...), $ids)));
    $unknown = array_values(array_diff($ids, array_keys($this->model()['roles'])));
    return $unknown ? $this->unknown($unknown) : ['ids' => $ids, 'meta' => $meta];
  }

  /**
   * Night order for known ids; a script's _meta order wins over the sheet.
   */
  public function nightOrder(array $ids, string $night, array $meta = []): array {
    $model = $this->model();
    $order = is_array($meta[$night . 'Night'] ?? NULL) ? $meta[$night . 'Night'] : $model['night'][$night];
    $in_set = array_flip($ids);
    $rows = [];
    foreach ($order as $entry) {
      $id = is_string($entry) ? self::normalize($entry) : '';
      if (isset(self::MARKERS[$id])) {
        $rows[] = [
          'position' => count($rows) + 1,
          'id' => $id,
          'name' => self::MARKERS[$id],
          'team' => NULL,
          'reminder' => NULL,
        ];
      }
      elseif (isset($in_set[$id])) {
        $role = $model['roles'][$id];
        $rows[] = [
          'position' => count($rows) + 1,
          'id' => $id,
          'name' => $role['name'],
          'team' => $role['team'],
          'reminder' => $role[$night . 'NightReminder'] ?? '',
        ];
      }
    }
    return $rows;
  }

  /**
   * Jinxes whose two characters are both in the set.
   */
  public function jinxesBetween(array $ids): array {
    $in_set = array_flip($ids);
    return array_values(array_filter(
      $this->model()['jinxes'],
      fn(array $jinx): bool => isset($in_set[$jinx['a']], $in_set[$jinx['b']]),
    ));
  }

  /**
   * Base counts plus in-set characters whose ability changes setup, unapplied.
   */
  public function setup(int $players, array $ids): array {
    $roles = $this->model()['roles'];
    $modifiers = [];
    foreach ($ids as $id) {
      if ($roles[$id]['setup'] ?? FALSE) {
        $modifiers[] = [
          'id' => $id,
          'name' => $roles[$id]['name'],
          'team' => $roles[$id]['team'],
          'ability' => $roles[$id]['ability'],
        ];
      }
    }
    return ['players' => $players, 'counts' => self::SETUP[min($players, 15)], 'modifiers' => $modifiers];
  }

  /**
   * Whole-word search; every query word must appear, name hits rank first.
   */
  public function search(string $query, ?string $team = NULL, ?string $edition = NULL, int $limit = 20): array {
    $patterns = array_map(
      fn(string $word): string => '/\b' . preg_quote($word, '/') . '\b/iu',
      preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY),
    );
    $edition = $edition === NULL ? NULL : self::normalize($edition);
    $hits = [];
    foreach ($this->model()['roles'] as $role) {
      $fields = [
        $role['name'],
        $role['ability'],
        $role['firstNightReminder'] ?? '',
        $role['otherNightReminder'] ?? '',
        implode(' · ', $role['reminders'] ?? []),
      ];
      $text = implode("\n", $fields);
      $matched = count(array_filter($patterns, fn(string $pattern): bool => preg_match($pattern, $text) === 1));
      if ($patterns && $matched === count($patterns) && (!$team || $role['team'] === $team) && (!$edition || $role['edition'] === $edition)) {
        foreach ($fields as $rank => $field) {
          if (preg_match($patterns[0], $field, $match, PREG_OFFSET_CAPTURE)) {
            $start = max(0, $match[0][1] - 40);
            $hits[] = [
              'rank' => $rank,
              'id' => $role['id'],
              'name' => $role['name'],
              'team' => $role['team'],
              'edition' => $role['edition'],
              'snippet' => ($start ? '…' : '') . mb_strcut($field, $start, 120) . (strlen($field) > $start + 120 ? '…' : ''),
            ];
            break;
          }
        }
      }
    }
    usort($hits, fn(array $x, array $y): int => [$x['rank'], $x['name']] <=> [$y['rank'], $y['name']]);
    return array_map(fn(array $hit): array => array_diff_key($hit, ['rank' => TRUE]), array_slice($hits, 0, $limit));
  }

  /**
   * Builds the in-memory model once per data change.
   */
  private function model(): array {
    $cached = $this->cache->get('ghostwriter:model');
    if ($cached) {
      return $cached->data;
    }
    $files = [];
    foreach (DataSync::FILES as $file) {
      $path = DataSync::DIR . '/' . $file;
      if (!file_exists($path)) {
        throw new DataMissingException();
      }
      $files[$file] = json_decode(file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    }

    $model = [
      'roles' => [],
      'editions' => [],
      'jinxes' => [],
      'night' => [
        'first' => $files['nightsheet.json']['firstNight'],
        'other' => $files['nightsheet.json']['otherNight'],
      ],
    ];
    $pairs = [];
    foreach ($files['jinxes.json'] as $entry) {
      foreach ($entry['jinx'] as $jinx) {
        $pairs[] = [$entry['id'], $jinx['id'], $jinx['reason']];
      }
    }
    foreach ($files['roles.json'] as $role) {
      $model['roles'][$role['id']] = $role;
      $model['editions'][$role['edition']][$role['team']][] = ['id' => $role['id'], 'name' => $role['name']];
      foreach ($role['jinxes'] ?? [] as $jinx) {
        $pairs[] = [$role['id'], $jinx['id'], $jinx['reason']];
      }
    }
    foreach ($pairs as [$a, $b, $reason]) {
      [$a, $b] = [min($a, $b), max($a, $b)];
      $model['jinxes']["$a|$b"] ??= ['a' => $a, 'b' => $b, 'reason' => $reason];
    }
    $this->cache->set('ghostwriter:model', $model, CacheBackendInterface::CACHE_PERMANENT, ['ghostwriter_data']);
    return $model;
  }

  /**
   * Error for unknown ids, with up to 3 close matches each.
   */
  private function unknown(array $ids): array {
    $known = array_keys($this->model()['roles']);
    $hints = [];
    foreach ($ids as $id) {
      $near = array_filter($known, fn(string $candidate): bool => levenshtein($id, $candidate) <= 3);
      usort($near, fn(string $x, string $y): int => levenshtein($id, $x) <=> levenshtein($id, $y));
      if ($near) {
        $hints[] = "$id: did you mean " . implode(', ', array_slice($near, 0, 3)) . '?';
      }
    }
    return [
      'error' => [
        'code' => 'unknown_character',
        'ids' => $ids,
        'hint' => implode(' ', $hints) ?: 'Ids are lowercase with no spaces or punctuation, e.g. pithag. Use search_characters or edition to find ids.',
      ],
    ];
  }

}
