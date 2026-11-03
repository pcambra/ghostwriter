<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ghostwriter\Plugin\mcp_server\ResourceProvider\GhostwriterStatus;
use Drupal\ghostwriter\Sync\DataSync;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mcp\Exception\ResourceReadException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the tool and resource plugins mcp_server runs.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class McpPluginsTest extends GhostwriterKernelTestBase {

  use UserCreationTrait;

  /**
   * Only administer ghostwriter unlocks the tools that write.
   */
  public function testWriteToolsNeedAdminPermission(): void {
    $this->installEntitySchema('user');
    $this->createUser([], 'superuser');
    $user = $this->createUser(['access mcp server']);
    $admin = $this->createUser(['access mcp server', 'administer ghostwriter']);
    $tools = $this->container->get('plugin.manager.mcp_server.tool');

    foreach (['refresh_data', 'script_import'] as $id) {
      $tool = $tools->createInstance($id);
      $this->assertFalse($tool->checkAccess($user)->isAllowed(), $id);
      $this->assertTrue($tool->checkAccess($admin)->isAllowed(), $id);
    }
  }

  /**
   * Install adds each resource plugin once; uninstall removes only those.
   */
  public function testInstallAndUninstall(): void {
    $http = new MockHandler();
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($http)]));
    foreach ([...DataSync::FILES, ...DataSync::FILES] as $file) {
      $http->append(new Response(200, [], file_get_contents($this->fixture($file))));
    }
    $other = ['id' => 'other', 'enabled' => TRUE, 'configuration' => []];
    $this->config('mcp_server.resource_providers')->set('plugins', [$other])->save();
    $this->container->get('module_handler')->loadInclude('ghostwriter', 'install');

    ghostwriter_install(FALSE);
    ghostwriter_install(FALSE);

    $this->assertSame(
      [['id' => 'ghostwriter_resources', 'enabled' => TRUE, 'configuration' => []]],
      $this->config('mcp_server.resource_template_providers')->get('plugins'),
    );
    $this->assertSame(
      [$other, ['id' => 'ghostwriter_status', 'enabled' => TRUE, 'configuration' => []]],
      $this->config('mcp_server.resource_providers')->get('plugins'),
    );
    $this->assertFileEquals($this->fixture('roles.json'), DataSync::DIR . '/roles.json');
    $later = ['id' => 'later', 'enabled' => TRUE, 'configuration' => []];
    $providers = $this->config('mcp_server.resource_providers');
    $providers->set('plugins', [...$providers->get('plugins'), $later])->save();

    ghostwriter_uninstall(FALSE);

    $this->assertSame([], $this->config('mcp_server.resource_template_providers')->get('plugins'));
    $this->assertSame([$other, $later], $this->config('mcp_server.resource_providers')->get('plugins'));
    $this->assertNull($this->container->get('state')->get('ghostwriter.sha256.roles.json'));
    $this->assertNull($this->container->get('state')->get('ghostwriter.source'));
    $this->assertDirectoryDoesNotExist(DataSync::DIR);
  }

  /**
   * Tools wrap data in the provenance envelope and return errors bare.
   */
  public function testToolEnvelope(): void {
    $tool = $this->container->get('plugin.manager.mcp_server.tool')->createInstance('character');
    $gateway = new ClientGateway($this->createMock(SessionInterface::class));

    $this->assertSame('data_missing', $tool->execute(['id' => 'lamplighter'], $gateway)['error']['code']);

    $this->repository();
    $result = $tool->execute(['id' => 'lamplighter'], $gateway);
    $this->assertSame(['data', 'meta'], array_keys($result));
    $this->assertSame('lamplighter', $result['data']['id']);
    $this->assertSame(['source', 'fetched_at', 'roles_sha256', 'notice'], array_keys($result['meta']));
    $this->assertSame(['error'], array_keys($tool->execute(['id' => 'nobody'], $gateway)));
  }

  /**
   * Resources carry the envelope and cache tags, and fail without data.
   */
  public function testResources(): void {
    $resources = $this->container->get('plugin.manager.mcp_server.resource_template_provider')->createInstance('ghostwriter_resources');
    try {
      $resources->getResourceContent('ghostwriter://character/lamplighter');
      $this->fail('Reading before the first sync should fail.');
    }
    catch (ResourceReadException $e) {
      $this->assertStringContainsString('ghostwriter:sync', $e->getMessage());
    }

    $repository = $this->repository();
    $character = $resources->getResourceContent('ghostwriter://character/Lamplighter');
    $this->assertSame(['ghostwriter_data'], $character->metadata->getCacheTags());
    $body = json_decode($character->content['text'], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('lamplighter', $body['data']['id']);
    $this->assertSame($repository->meta(), $body['meta']);

    $edition = json_decode($resources->getResourceContent('ghostwriter://edition/gw')->content['text'], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertEquals($repository->character('lamplighter'), $edition['data']['characters']['townsfolk'][0]);

    $this->container->get('ghostwriter.script_importer')->import(['lamplighter'], 'one');
    $script = $resources->getResourceContent('ghostwriter://script/one');
    $this->assertContains('config:ghostwriter_script_list', $script->metadata->getCacheTags());

    $this->assertNull($resources->getResourceContent('ghostwriter://character/nobody'));
    $this->assertNull($resources->getResourceContent('ghostwriter://other/lamplighter'));

    $status = $this->container->get('plugin.manager.mcp_server.resource_provider')->createInstance('ghostwriter_status')->getResourceContent(GhostwriterStatus::URI);
    $this->assertSame(0, $status->metadata->getCacheMaxAge());
    $this->assertTrue(json_decode($status->content['text'], TRUE, 512, JSON_THROW_ON_ERROR)['stale']);
  }

}
