<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests setup counts and modifiers.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class SetupTest extends GhostwriterKernelTestBase {

  /**
   * Table row by player count, 15+ capped, modifiers listed but not applied.
   */
  public function testSetup(): void {
    $repository = $this->repository();

    $this->assertSame(['townsfolk' => 5, 'outsider' => 1, 'minion' => 1, 'demon' => 1], $repository->setup(8, [])['counts']);
    $this->assertSame(['townsfolk' => 9, 'outsider' => 2, 'minion' => 3, 'demon' => 1], $repository->setup(18, [])['counts']);

    $set = $repository->resolveSet(['characters' => ['lamplighter', 'Ferryman', 'hollowking']]);
    $setup = $repository->setup(7, $set['ids']);
    $this->assertSame(['townsfolk' => 5, 'outsider' => 0, 'minion' => 1, 'demon' => 1], $setup['counts']);
    $this->assertSame(
      [['id' => 'ferryman', 'name' => 'Ferryman', 'team' => 'outsider', 'ability' => 'You start the game drunk.']],
      $setup['modifiers'],
    );
  }

}
