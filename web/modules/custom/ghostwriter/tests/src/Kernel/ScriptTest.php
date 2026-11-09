<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\ghostwriter\Data\DataMissingException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests saved scripts.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class ScriptTest extends GhostwriterKernelTestBase {

  /**
   * Importing before the first sync names the sync command.
   */
  public function testImportWithoutData(): void {
    $this->expectException(DataMissingException::class);
    $this->container->get('ghostwriter.script_importer')->import(['lamplighter'], 'early');
  }

  /**
   * Import, reuse by script_id, overwrite, and nothing saved on bad input.
   *
   * A _meta key with a dot saves; a name too long for an id is rejected.
   */
  public function testScripts(): void {
    $repository = $this->repository();
    $importer = $this->container->get('ghostwriter.script_importer');
    $script = [
      [
        'id' => '_meta',
        'name' => 'Gloom Wood',
        'author' => 'Tester',
        'firstNight' => ['dusk', 'lamplighter', 'whisperer', 'dawn'],
      ],
      'Lamplighter',
      ['id' => 'whisperer'],
      'ferryman',
    ];

    $this->assertSame([
      'id' => 'gloom_wood',
      'label' => 'Gloom Wood',
      'status' => 'created',
      'characters' => ['lamplighter', 'whisperer', 'ferryman'],
    ], $importer->import($script));
    $this->assertSame('updated', $importer->import($script)['status']);
    $this->assertSame('Tester', $repository->script('gloom_wood')['author']);

    $set = $repository->resolveSet(['script_id' => 'gloom_wood']);
    $this->assertSame(['dusk', 'lamplighter', 'whisperer', 'dawn'], array_column($repository->nightOrder($set['ids'], 'first', $set['meta']), 'id'));
    $this->assertCount(1, $repository->jinxesBetween($set['ids']));
    $this->assertSame(['ferryman'], array_column($repository->setup(7, $set['ids'])['modifiers'], 'id'));

    $this->assertSame('unknown_character', $importer->import(['lamplighter', 'nobody'], 'bad')['error']['code']);
    $this->assertSame('invalid_script', $importer->import(['lamplighter'])['error']['code']);
    $this->assertSame('invalid_script', $importer->import(['id' => 'bad'])['error']['code']);
    $this->assertSame('invalid_script', $importer->import(['_meta', 'lamplighter'], 'bad')['error']['code']);
    $this->assertSame('invalid_script', $repository->resolveSet(['script' => ['_meta', 'lamplighter']])['error']['code']);
    $both = $repository->resolveSet(['script_id' => 'gloom_wood', 'characters' => ['lamplighter']]);
    $this->assertSame('invalid_script', $both['error']['code']);

    $error = $repository->resolveSet(['script_id' => 'bad'])['error'];
    $this->assertSame('unknown_script', $error['code']);
    $this->assertSame('Known scripts: gloom_wood', $error['hint']);

    $dotted = ['id' => '_meta', 'name' => 'Dotted', 'a.b' => 1];
    $this->assertSame('created', $importer->import([$dotted, 'lamplighter'])['status']);
    $this->assertSame($dotted, $repository->script('dotted')['meta']);
    $too_long = ['id' => '_meta', 'name' => str_repeat('x', 256)];
    $this->assertSame('invalid_script', $importer->import([$too_long, 'lamplighter'])['error']['code']);
  }

  /**
   * The update turns scripts saved as config into content.
   */
  public function testUpdateMovesConfigScripts(): void {
    $update_manager = $this->container->get('entity.definition_update_manager');
    $update_manager->uninstallEntityType($update_manager->getEntityType('ghostwriter_script'));
    $this->container->get('entity.last_installed_schema.repository')->setLastInstalledDefinition(new ConfigEntityType([
      'id' => 'ghostwriter_script',
      'provider' => 'ghostwriter',
      'class' => ConfigEntityBase::class,
      'config_prefix' => 'script',
    ]));
    $meta = ['id' => '_meta', 'name' => 'Gloom Wood', 'firstNight' => ['dusk', 'whisperer', 'lamplighter', 'dawn']];
    $config_storage = $this->container->get('config.storage');
    $config_storage->write('ghostwriter.script.gloom_wood', [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'gloom_wood',
      'label' => 'Gloom Wood',
      'author' => 'Tester',
      'characters' => ['lamplighter', 'whisperer'],
      'meta' => $meta,
    ]);
    $config_storage->write('ghostwriter.script.bare', [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'bare',
      'label' => 'bare',
      'author' => '',
      'characters' => ['ferryman'],
      'meta' => [],
    ]);
    $this->container->get('module_handler')->loadInclude('ghostwriter', 'install');

    ghostwriter_update_11001();

    $repository = $this->repository();
    $this->assertSame([
      'id' => 'gloom_wood',
      'label' => 'Gloom Wood',
      'author' => 'Tester',
      'characters' => ['lamplighter', 'whisperer'],
      'meta' => $meta,
    ], $repository->script('gloom_wood'));
    $this->assertSame([
      'id' => 'bare',
      'label' => 'bare',
      'author' => '',
      'characters' => ['ferryman'],
      'meta' => [],
    ], $repository->script('bare'));
    $this->assertSame([], $this->container->get('config.factory')->listAll('ghostwriter.script.'));
    $this->assertArrayNotHasKey('ghostwriter_script', $update_manager->getChangeList());
  }

}
