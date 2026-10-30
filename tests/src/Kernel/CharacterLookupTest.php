<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use Drupal\ghostwriter\Data\CharacterRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests character, edition and search lookups.
 */
#[Group('ghostwriter')]
#[RunTestsInSeparateProcesses]
final class CharacterLookupTest extends GhostwriterKernelTestBase {

  /**
   * Night flags come from the nightsheet; search ranks name over ability.
   */
  public function testLookups(): void {
    $repository = $this->repository();

    $king = $repository->character('Hollow King');
    $this->assertSame(
      ['id' => 'hollowking', 'team' => 'demon', 'edition' => 'gw', 'firstNight' => FALSE, 'otherNight' => TRUE],
      array_intersect_key($king, array_flip(['id', 'team', 'edition', 'firstNight', 'otherNight'])),
    );

    $edition = $repository->edition('GW');
    $this->assertSame(CharacterRepository::TEAMS, array_keys($edition['characters']));
    $this->assertSame(['lamplighter', 'bellringer'], array_column($edition['characters']['townsfolk'], 'id'));
    $this->assertSame('Known editions: gw, gx', $repository->edition('tb')['error']['hint']);

    $this->assertSame(['bellringer', 'ferryman'], array_column($repository->search('drunk'), 'id'));
    $this->assertSame(['ferryman'], array_column($repository->search('drunk', 'outsider'), 'id'));
    $this->assertSame(['lamplighter'], array_column($repository->search('lamp'), 'id'));
    $this->assertSame([], $repository->search('light'));
    $this->assertSame(['whisperer'], array_column($repository->search('whisperer player'), 'id'));
  }

}
