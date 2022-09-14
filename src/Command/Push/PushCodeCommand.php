<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PushCodeCommand.
 */
class PushCodeCommand extends PullCommandBase {

  protected static $defaultName = 'push:code';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Push code from your IDE to a Cloud Platform environment')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln("Please use <options=bold>git</> to push code changes upstream.");

    return 0;
  }

}
