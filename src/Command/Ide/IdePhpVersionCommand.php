<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
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
      ->addArgument('version', InputArgument::REQUIRED, 'The PHP version')
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
    $this->restartPhp();

    return 0;
  }

  /**
   * Restart PHP.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function restartPhp(): void
  {
    $this->logger->info('Restarting PHP...');
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'restart',
      'php-fpm',
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to restart PHP in the IDE.');
    }
    $this->logger->info('Restarting bash...');
    $process = $this->localMachineHelper->execute([
      'bash',
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to restart bash in the IDE.');
    }
  }


}
