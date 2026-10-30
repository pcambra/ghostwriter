<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\ghostwriter\Form\RefreshForm;
use Drupal\ghostwriter\Sync\DataSync;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the all-or-nothing sync contract.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class DataSyncTest extends GhostwriterKernelTestBase {

  /**
   * Queue of fake HTTP responses.
   */
  private MockHandler $http;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->http = new MockHandler();
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($this->http)]));
  }

  /**
   * Valid files land with State written; a later 304 skips them.
   */
  public function testSyncThenNotModified(): void {
    $cache = $this->container->get('cache.default');
    $cache->set('probe', TRUE, Cache::PERMANENT, ['ghostwriter_data']);
    $this->config('ghostwriter.settings')->set('base_url', 'https://mirror.example/data/')->save();
    $sync = $this->container->get('ghostwriter.sync');

    $this->http->append(...$this->responses());
    $result = $sync->sync();
    $this->assertSame('https://mirror.example/data/nightsheet.json', (string) $this->http->getLastRequest()->getUri());
    $this->assertSame('mirror.example', $this->container->get('ghostwriter.characters')->meta()['source']);
    $this->assertSame(DataSync::FILES, $result['changed']);
    $this->assertSame([], $result['unchanged']);
    foreach (DataSync::FILES as $file) {
      $this->assertFileEquals($this->fixture($file), DataSync::DIR . '/' . $file);
    }
    $this->assertSame(hash_file('sha256', $this->fixture('roles.json')), $result['roles_sha256']);
    $this->assertSame('"etag-jinxes.json"', $this->container->get('state')->get('ghostwriter.etag.jinxes.json'));
    $this->assertFalse($cache->get('probe'));

    $this->http->append(new Response(304), new Response(304), new Response(304));
    $result = $sync->sync();
    $this->assertSame([], $result['changed']);
    $this->assertSame(DataSync::FILES, $result['unchanged']);
    $this->assertSame('"etag-nightsheet.json"', $this->http->getLastRequest()->getHeaderLine('If-None-Match'));
  }

  /**
   * A nightsheet id missing from roles rejects the sync and keeps old files.
   */
  public function testInvalidSyncKeepsPreviousFiles(): void {
    $sync = $this->container->get('ghostwriter.sync');
    $this->http->append(...$this->responses());
    $sync->sync();

    $nightsheet = json_decode(file_get_contents($this->fixture('nightsheet.json')), TRUE);
    $nightsheet['firstNight'][] = 'nobody';
    $this->http->append(...$this->responses(['nightsheet.json' => json_encode($nightsheet)]));
    $result = $sync->sync(TRUE);

    $this->assertSame('sync_failed', $result['error']['code']);
    $this->assertStringContainsString('nobody', $result['error']['message']);
    foreach (DataSync::FILES as $file) {
      $this->assertFileEquals($this->fixture($file), DataSync::DIR . '/' . $file);
    }
    $this->assertDirectoryDoesNotExist(DataSync::DIR . '/tmp');
  }

  /**
   * Files with the wrong JSON shape reject the sync and keep old files.
   */
  public function testMalformedShapesKeepPreviousFiles(): void {
    $sync = $this->container->get('ghostwriter.sync');
    $this->http->append(...$this->responses());
    $sync->sync();

    $jinxes = json_decode(file_get_contents($this->fixture('jinxes.json')), TRUE);
    unset($jinxes[0]['jinx']);
    foreach (['roles.json' => '"roles"', 'jinxes.json' => json_encode($jinxes)] as $file => $body) {
      $this->http->reset();
      $this->http->append(...$this->responses([$file => $body]));
      $result = $sync->sync(TRUE);

      $this->assertSame('sync_failed', $result['error']['code'] ?? NULL, $file);
      $this->assertStringContainsString($file, $result['error']['message']);
      $this->assertDirectoryDoesNotExist(DataSync::DIR . '/tmp');
    }
    foreach (DataSync::FILES as $file) {
      $this->assertFileEquals($this->fixture($file), DataSync::DIR . '/' . $file);
    }
  }

  /**
   * A move failing midway puts back the files already replaced.
   */
  public function testFailedMoveRestoresPreviousFiles(): void {
    $this->repository();
    $this->container->set('file_system', new class(
      $this->container->get('stream_wrapper_manager'),
      $this->container->get('settings'),
    ) extends FileSystem {

      /**
       * {@inheritdoc}
       */
      public function move($source, $destination, $fileExists = FileExists::Rename) {
        if ($destination === DataSync::DIR . '/jinxes.json') {
          throw new FileException('Disk full.');
        }
        return parent::move($source, $destination, $fileExists);
      }

    });
    $bodies = [];
    foreach (DataSync::FILES as $file) {
      $bodies[$file] = file_get_contents($this->fixture($file)) . "\n";
    }
    $this->http->append(...$this->responses($bodies));

    $result = $this->container->get('ghostwriter.sync')->sync(TRUE);

    $this->assertSame('sync_failed', $result['error']['code'] ?? NULL);
    foreach (DataSync::FILES as $file) {
      $this->assertFileEquals($this->fixture($file), DataSync::DIR . '/' . $file);
    }
    $this->assertDirectoryDoesNotExist(DataSync::DIR . '/tmp');
  }

  /**
   * A sync started while another one runs changes nothing.
   */
  public function testOverlappingSyncIsRejected(): void {
    $database = $this->container->get('database');
    $this->container->set('lock', new DatabaseLockBackend($database));
    $other = new DatabaseLockBackend($database);
    $this->assertTrue($other->acquire('ghostwriter_sync'));
    $this->http->append(...$this->responses());

    $result = $this->container->get('ghostwriter.sync')->sync();

    $this->assertSame('sync_running', $result['error']['code'] ?? NULL);
    $this->assertCount(3, $this->http);
    $this->assertFileDoesNotExist(DataSync::DIR . '/roles.json');
  }

  /**
   * The admin form syncs and reports, forced or not.
   */
  public function testRefreshForm(): void {
    $form_builder = $this->container->get('form_builder');
    $messenger = $this->container->get('messenger');

    $this->http->append(...$this->responses());
    $form_state = new FormState();
    $form_builder->submitForm(RefreshForm::class, $form_state);
    $this->assertSame(
      'Changed: roles.json, jinxes.json, nightsheet.json. Unchanged: none.',
      (string) $messenger->deleteByType('status')[0],
    );

    $this->http->append(...$this->responses());
    $form_state = (new FormState())->setValues(['force' => 1]);
    $form_builder->submitForm(RefreshForm::class, $form_state);
    $this->assertSame('', $this->http->getLastRequest()->getHeaderLine('If-None-Match'));
    $this->assertStringStartsWith('Changed: roles.json', (string) $messenger->deleteByType('status')[0]);

    $this->http->append(new Response(500));
    $form_state = new FormState();
    $form_builder->submitForm(RefreshForm::class, $form_state);
    $this->assertNotEmpty($messenger->deleteByType('error'));
  }

  /**
   * One 200 response per file, bodies from fixtures unless overridden.
   */
  private function responses(array $bodies = []): array {
    return array_map(fn(string $file): Response => new Response(
      200,
      ['ETag' => "\"etag-$file\"", 'Last-Modified' => 'Mon, 26 Oct 2026 12:00:00 GMT'],
      $bodies[$file] ?? file_get_contents($this->fixture($file)),
    ), DataSync::FILES);
  }

}
