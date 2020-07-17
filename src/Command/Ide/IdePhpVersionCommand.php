<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdePhpVersionCommand.
 */
class IdePhpVersionCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:php-version';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Change the PHP version in the current IDE')
      ->setHidden(AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $version = $input->getArgument('version');
    // @todo Validate version.
    $this->localMachineHelper->getFilesystem()->dumpFile('/home/ide/configs/php/.version', $version);


    return 0;
  }

}
