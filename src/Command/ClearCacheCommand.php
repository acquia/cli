<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

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
    self::clearCaches();
    $output->writeln('Acquia CLI caches were cleared.');

    return 0;
  }

  /**
   * Clear caches.
   */
  public static function clearCaches(): void {
    $cache = self::getAliasCache();
    $cache->clear();
    $system_cache_dir = Path::join(sys_get_temp_dir(), 'symphony-cache');
    $fs = new Filesystem();
    $fs->remove([$system_cache_dir]);
  }

}
