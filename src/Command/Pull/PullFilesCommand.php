<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pull:files', description: 'Copy Drupal public files from a Cloud Platform environment to your local environment')]
class PullFilesCommand extends PullCommandBase {

  protected function configure(): void {
    $this
      ->acceptEnvironmentId()
      ->acceptSite();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    parent::execute($input, $output);
    $this->pullFiles($input, $output);

    return Command::SUCCESS;
  }

}
