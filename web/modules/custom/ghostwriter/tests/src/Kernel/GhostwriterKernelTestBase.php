<?php

declare(strict_types=1);

namespace Drupal\Tests\ghostwriter\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\ghostwriter\Sync\DataSync;

/**
 * Private files plus synthetic fixtures; never TPI data.
 */
abstract class GhostwriterKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'mcp_server', 'ghostwriter'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setSetting('file_private_path', $this->container->getParameter('site.path') . '/private');
    $this->installConfig(['ghostwriter']);
  }

  /**
   * Path of a fixture file.
   */
  protected function fixture(string $file): string {
    return dirname(__DIR__, 2) . '/fixtures/' . $file;
  }

  /**
   * Puts the fixtures where a sync would, and returns the repository.
   */
  protected function repository(): CharacterRepository {
    $dir = DataSync::DIR;
    $this->container->get('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
    foreach (DataSync::FILES as $file) {
      copy($this->fixture($file), "$dir/$file");
    }
    return $this->container->get('ghostwriter.characters');
  }

}
