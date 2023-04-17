<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PullFilesCommand extends PullCommandBase {

  protected static $defaultName = 'pull:files';

  protected function configure(): void {
    $this->setDescription('Copy files from a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->acceptSite()
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    parent::execute($input, $output);
    $this->pullFiles($input, $output);

    return 0;
  }

}
