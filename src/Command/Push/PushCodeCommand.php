<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'push:code')]
class PushCodeCommand extends PullCommandBase {

  /**
   * @var string
   */
  // phpcs:ignore
  protected static $defaultDescription = 'Push code from your IDE to a Cloud Platform environment';

  protected function configure(): void {
    $this
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln("Use <options=bold>git</> to push code changes upstream.");

    return Command::SUCCESS;
  }

}
