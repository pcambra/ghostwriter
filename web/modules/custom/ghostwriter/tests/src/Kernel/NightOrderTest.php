<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests night order resolution.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class NightOrderTest extends GhostwriterKernelTestBase {

  /**
   * Sheet order, _meta override, and no partial order on unknown ids.
   */
  public function testNightOrder(): void {
    $repository = $this->repository();
    $set = $repository->resolveSet(['characters' => ['Lamplighter', 'whisperer', 'Hollow King', 'bellringer']]);

    $first = $repository->nightOrder($set['ids'], 'first', $set['meta']);
    $this->assertSame(['dusk', 'minioninfo', 'demoninfo', 'whisperer', 'lamplighter', 'dawn'], array_column($first, 'id'));
    $this->assertSame(range(1, 6), array_column($first, 'position'));
    $this->assertSame('Show the Lamplighter a lamp.', $first[4]['reminder']);
    $this->assertNull($first[0]['team']);

    $other = $repository->nightOrder($set['ids'], 'other', $set['meta']);
    $this->assertSame(['dusk', 'hollowking', 'bellringer', 'lamplighter', 'dawn'], array_column($other, 'id'));

    $set = $repository->resolveSet([
      'script' => [
        ['id' => '_meta', 'name' => 'Custom', 'firstNight' => ['dusk', 'lamplighter', 'whisperer', 'dawn']],
        'lamplighter',
        ['id' => 'whisperer'],
      ],
    ]);
    $first = $repository->nightOrder($set['ids'], 'first', $set['meta']);
    $this->assertSame(['dusk', 'lamplighter', 'whisperer', 'dawn'], array_column($first, 'id'));

    $result = $repository->resolveSet(['characters' => ['lamplighter', 'lamplightr']]);
    $this->assertSame(['error'], array_keys($result));
    $this->assertSame('unknown_character', $result['error']['code']);
    $this->assertSame(['lamplightr'], $result['error']['ids']);
    $this->assertStringContainsString('lamplighter', $result['error']['hint']);
  }

}
