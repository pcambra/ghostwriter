<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the undirected jinx index.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class JinxesTest extends GhostwriterKernelTestBase {

  /**
   * A pair listed from both sides is returned once; half pairs never.
   */
  public function testJinxesBetween(): void {
    $repository = $this->repository();
    $expected = [
      [
        'a' => 'lamplighter',
        'b' => 'whisperer',
        'reason' => 'If the Whisperer whispers to the Lamplighter, the lamp goes out.',
      ],
    ];
    $this->assertSame($expected, $repository->jinxesBetween(['whisperer', 'lamplighter', 'bellringer']));
    $this->assertSame($expected, $repository->jinxesBetween(['lamplighter', 'whisperer']));
    $this->assertSame([], $repository->jinxesBetween(['whisperer', 'hollowking']));
    $this->assertSame([['with' => 'whisperer', 'reason' => $expected[0]['reason']]], $repository->character('lamplighter')['jinxes']);
  }

}
