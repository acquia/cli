<?php

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullCommand extends PullCommandBase {

  protected static $defaultName = 'pull:all';

  protected function commandRequiresDatabase(): bool {
    return TRUE;
  }

  protected function configure(): void {
    $this->setAliases(['refresh', 'pull'])
      ->setDescription('Copy code, database, and files from a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->acceptSite()
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->addOption('no-code', NULL, InputOption::VALUE_NONE, 'Do not refresh code from remote repository')
      ->addOption('no-files', NULL, InputOption::VALUE_NONE, 'Do not refresh files')
      ->addOption('no-databases', NULL, InputOption::VALUE_NONE, 'Do not refresh databases')
      ->addOption(
            'no-scripts',
            NULL,
            InputOption::VALUE_NONE,
            'Do not run any additional scripts after code and database are copied. E.g., composer install , drush cache-rebuild, etc.'
        );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    parent::execute($input, $output);

    if (!$input->getOption('no-code')) {
      $this->pullCode($input, $output);
    }

    if (!$input->getOption('no-files')) {
      $this->pullFiles($input, $output);
    }

    if (!$input->getOption('no-databases')) {
      $this->pullDatabase($input, $output);
    }

    if (!$input->getOption('no-scripts')) {
      $this->executeAllScripts($input, $this->getOutputCallback($output, $this->checklist));
    }

    return Command::SUCCESS;
  }

}
