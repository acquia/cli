<?php

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullCodeCommand extends PullCommandBase {

  protected static $defaultName = 'pull:code';

  protected function commandRequiresDatabase(): bool {
    return TRUE;
  }

  protected function configure(): void {
    $this->setDescription('Copy code from a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE,
        'Do not run any additional scripts after code is pulled. E.g., composer install , drush cache-rebuild, etc.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->pullCode($input, $output);
    $this->checkEnvironmentPhpVersions($this->sourceEnvironment);
    $this->matchIdePhpVersion($output, $this->sourceEnvironment);
    if (!$input->getOption('no-scripts')) {
      $outputCallback = $this->getOutputCallback($output, $this->checklist);
      $this->runComposerScripts($outputCallback);
      $this->runDrushCacheClear($outputCallback);
    }

    return Command::SUCCESS;
  }

}
