<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pull:run-scripts', description: 'Execute post pull scripts')]
class PullScriptsCommand extends PullCommandBase {

  protected function configure(): void {
    $this
      ->acceptEnvironmentId()
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->executeAllScripts($input, $this->getOutputCallback($output, $this->checklist));

    return Command::SUCCESS;
  }

}
