<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullCodeCommand extends PullCommandBase {

  protected static $defaultName = 'pull:code';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Copy code from a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE,
        'Do not run any additional scripts after code is pulled. E.g., composer install , drush cache-rebuild, etc.')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->pullCode($input, $output);
    $this->checkEnvironmentPhpVersions($this->sourceEnvironment);
    $this->matchIdePhpVersion($output, $this->sourceEnvironment);
    if (!$input->getOption('no-scripts')) {
      $output_callback = $this->getOutputCallback($output, $this->checklist);
      $this->runComposerScripts($output_callback);
      $this->runDrushCacheClear($output_callback);
    }

    return 0;
  }

}
