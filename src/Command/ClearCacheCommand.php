<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Class ClearCacheCommand.
 */
class ClearCacheCommand extends CommandBase {

  protected static $defaultName = 'clear-caches';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Clears local Acquia CLI caches')
      ->setAliases(['cc', 'cr']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    self::clearCaches($this->tmpDir);
    $output->writeln('Acquia CLI caches were cleared.');

    return 0;
  }

  /**
   * Clear caches.
   */
  public static function clearCaches(string $temp_dir): void {
    $cache = self::getAliasCache($temp_dir);
    $cache->clear();
    $system_cache_dir = Path::join($temp_dir, 'symphony-cache');
    $fs = new Filesystem();
    $fs->remove([$system_cache_dir]);
  }

}
