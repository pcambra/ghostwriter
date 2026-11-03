<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ghostwriter\Data\CharacterRepository;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Downloads, validates and stores the official TPI data files.
 */
final class DataSync {

  public const FILES = ['roles.json', 'jinxes.json', 'nightsheet.json'];

  public const DIR = 'private://ghostwriter';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly FileSystemInterface $fileSystem,
    private readonly StateInterface $state,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    #[Autowire(service: 'logger.channel.ghostwriter')]
    private readonly LoggerInterface $logger,
    private readonly TimeInterface $time,
    #[Autowire(service: 'lock')]
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Syncs all files or none; returns the result or a sync_failed error.
   *
   * Returns a sync_running error while another sync holds the lock.
   */
  public function sync(bool $force = FALSE): array {
    if (!$this->lock->acquire('ghostwriter_sync', 120)) {
      return ['error' => ['code' => 'sync_running', 'message' => 'Another sync is running.']];
    }
    $base_url = $this->configFactory->get('ghostwriter.settings')->get('base_url');
    $tmp = self::DIR . '/tmp';
    $changed = $unchanged = $data = $moved = [];
    try {
      $this->fileSystem->prepareDirectory($tmp, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      foreach (self::FILES as $file) {
        $live = self::DIR . '/' . $file;
        $headers = [];
        if (!$force && file_exists($live)) {
          $headers = array_filter([
            'If-None-Match' => $this->state->get("ghostwriter.etag.$file"),
            'If-Modified-Since' => $this->state->get("ghostwriter.last_modified.$file"),
          ]);
        }
        $response = $this->httpClient->request('GET', $base_url . $file, ['headers' => $headers]);
        if ($response->getStatusCode() === 304) {
          $unchanged[] = $file;
          $body = file_get_contents($live);
        }
        else {
          $body = (string) $response->getBody();
          $this->fileSystem->saveData($body, "$tmp/$file", FileExists::Replace);
          $changed[$file] = [
            "ghostwriter.etag.$file" => $response->getHeaderLine('ETag'),
            "ghostwriter.last_modified.$file" => $response->getHeaderLine('Last-Modified'),
          ];
        }
        $data[$file] = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data[$file])) {
          throw new \UnexpectedValueException("$file: not a JSON array or object.");
        }
      }
      $this->validate($data['roles.json'], $data['jinxes.json'], $data['nightsheet.json']);
      foreach (array_keys($changed) as $file) {
        $live = self::DIR . '/' . $file;
        if (file_exists($live)) {
          $this->fileSystem->copy($live, "$tmp/$file.previous", FileExists::Replace);
        }
        $this->fileSystem->move("$tmp/$file", $live, FileExists::Replace);
        $moved[] = $file;
      }
    }
    catch (GuzzleException | FileException | \JsonException | \UnexpectedValueException $e) {
      foreach ($moved as $file) {
        $live = self::DIR . '/' . $file;
        if (file_exists("$tmp/$file.previous")) {
          $this->fileSystem->move("$tmp/$file.previous", $live, FileExists::Replace);
        }
        else {
          $this->fileSystem->delete($live);
        }
      }
      $this->fileSystem->deleteRecursive($tmp);
      $this->lock->release('ghostwriter_sync');
      $this->logger->error('Sync failed, previous files kept: @message', ['@message' => $e->getMessage()]);
      return ['error' => ['code' => 'sync_failed', 'message' => $e->getMessage()]];
    }
    $this->fileSystem->deleteRecursive($tmp);

    $now = $this->time->getCurrentTime();
    $values = ['ghostwriter.fetched_at' => $now, 'ghostwriter.source' => parse_url($base_url, PHP_URL_HOST)];
    foreach ($changed as $file => $file_headers) {
      $values += $file_headers;
      $values["ghostwriter.sha256.$file"] = hash_file('sha256', self::DIR . '/' . $file);
    }
    $this->state->setMultiple($values);
    $this->cacheTagsInvalidator->invalidateTags(['ghostwriter_data']);
    $this->lock->release('ghostwriter_sync');
    return [
      'changed' => array_keys($changed),
      'unchanged' => $unchanged,
      'fetched_at' => gmdate('c', $now),
      'roles_sha256' => $this->state->get('ghostwriter.sha256.roles.json'),
    ];
  }

  /**
   * Throws when the files do not reference each other consistently.
   */
  private function validate(array $roles, array $jinxes, array $nightsheet): void {
    $ids = [];
    foreach ($roles as $role) {
      foreach (['id', 'name', 'team', 'edition', 'ability'] as $key) {
        if (!is_string($role[$key] ?? NULL)) {
          throw new \UnexpectedValueException("roles.json: a role has no '$key'.");
        }
      }
      if (!in_array($role['team'], CharacterRepository::TEAMS, TRUE)) {
        throw new \UnexpectedValueException("roles.json: unknown team '{$role['team']}' on '{$role['id']}'.");
      }
      if (isset($ids[$role['id']])) {
        throw new \UnexpectedValueException("roles.json: duplicate id '{$role['id']}'.");
      }
      $ids[$role['id']] = TRUE;
    }

    foreach (['firstNight', 'otherNight'] as $night) {
      if (!is_array($nightsheet[$night] ?? NULL)) {
        throw new \UnexpectedValueException("nightsheet.json: no '$night' list.");
      }
      foreach ($nightsheet[$night] as $id) {
        if (!is_string($id) || (!isset($ids[$id]) && !isset(CharacterRepository::MARKERS[$id]))) {
          throw new \UnexpectedValueException(sprintf("nightsheet.json: '%s' in %s is not a role id.", json_encode($id), $night));
        }
      }
    }

    $pairs = [];
    foreach ($jinxes as $entry) {
      if (!is_array($entry['jinx'] ?? NULL)) {
        throw new \UnexpectedValueException(sprintf("jinxes.json: %s has no 'jinx' list.", json_encode($entry['id'] ?? NULL)));
      }
      foreach ($entry['jinx'] as $jinx) {
        $pairs[] = [$entry['id'] ?? NULL, $jinx['id'] ?? NULL, $jinx['reason'] ?? NULL];
      }
    }
    foreach ($roles as $role) {
      foreach ($role['jinxes'] ?? [] as $jinx) {
        $pairs[] = [$role['id'], $jinx['id'] ?? NULL, $jinx['reason'] ?? NULL];
      }
    }
    foreach ($pairs as [$a, $b, $reason]) {
      if (!is_string($a) || !is_string($b) || !isset($ids[$a], $ids[$b]) || !is_string($reason)) {
        throw new \UnexpectedValueException(sprintf('jinxes: invalid pair %s / %s.', json_encode($a), json_encode($b)));
      }
    }
  }

}
