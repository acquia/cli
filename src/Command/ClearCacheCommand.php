<?php

namespace Acquia\Cli\Command;

use AcquiaCloudApi\Endpoints\Logs;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
  }

}
