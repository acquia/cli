<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:logout', description: 'Remove Cloud API key and secret from local machine.', aliases: ['logout'])]
class AuthLogoutCommand extends CommandBase {

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($this->cloudApiClientService->isMachineAuthenticated()) {
      $answer = $this->io->confirm('Are you sure you\'d like to unset the Acquia Cloud API key for Acquia CLI?');
      if (!$answer) {
        return Command::SUCCESS;
      }
    }
    $this->datastoreCloud->remove('acli_key');

    $output->writeln("Unset the Acquia Cloud API key for Acquia CLI</info>");

    return Command::SUCCESS;
  }

}
