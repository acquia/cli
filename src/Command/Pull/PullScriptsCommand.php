<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PullScriptsCommand.
 */
class PullScriptsCommand extends PullCommandBase {

  protected static $defaultName = 'pull:run-scripts';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Execute post pull scripts')
      ->acceptEnvironmentId()
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->executeAllScripts($input, $this->getOutputCallback($output, $this->checklist));

    return 0;
  }

}
