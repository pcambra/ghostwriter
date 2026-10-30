<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

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
    $both = $repository->resolveSet(['script_id' => 'gloom_wood', 'characters' => ['lamplighter']]);
    $this->assertSame('invalid_script', $both['error']['code']);

    $error = $repository->resolveSet(['script_id' => 'bad'])['error'];
    $this->assertSame('unknown_script', $error['code']);
    $this->assertSame('Known scripts: gloom_wood', $error['hint']);
  }

}
