<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PullFilesCommand.
 */
class PullFilesCommand extends PullCommandBase {

  protected static $defaultName = 'pull:files';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Copy files from a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->acceptSite()
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
    parent::execute($input, $output);
    $this->pullFiles($input, $output);

    return 0;
  }

}
