<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln("Use <options=bold>git</> to push code changes upstream.");

    return 0;
  }

}
