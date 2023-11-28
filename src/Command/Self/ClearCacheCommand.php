<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'self:clear-caches', 'Clears local Acquia CLI caches', ['cc', 'cr'])]
class ClearCacheCommand extends CommandBase {

  protected function configure(): void
  {
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    self::clearCaches();
    $output->writeln('Acquia CLI caches were cleared.');

    return Command::SUCCESS;
  }

  /**
   * Clear caches.
   */
  public static function clearCaches(): void {
    $cache = self::getAliasCache();
    $cache->clear();
    $systemCacheDir = Path::join(sys_get_temp_dir(), 'symphony-cache');
    $fs = new Filesystem();
    $fs->remove([$systemCacheDir]);
  }

}
