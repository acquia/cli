<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:logout', description: 'Remove Acquia Cloud API credentials', aliases: ['logout'])]
final class AuthLogoutCommand extends CommandBase {

  protected function configure(): void {
    $this->addOption('delete', NULL, InputOption::VALUE_NEGATABLE, 'Delete the active Acquia Cloud API credentials');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$this->cloudApiClientService->isMachineAuthenticated()) {
      throw new AcquiaCliException('You are not authenticated and therefore cannot logout');
    }
    $activeKey = $this->datastoreCloud->get('acli_key');
    $output->writeln("<info>The active key <options=bold>$activeKey</> will be unset. You may also delete the active credentials entirely.</info>");
    $delete = $this->determineOption('delete', FALSE, NULL, NULL, TRUE);
    $this->datastoreCloud->remove('acli_key');
    if ($delete) {
      $this->datastoreCloud->remove("keys.$activeKey");
      $output->writeln("The active Acquia Cloud API credentials were deleted</info>");
    }
    else {
      $output->writeln("The active Acquia Cloud API credentials were unset</info>");
    }

    return Command::SUCCESS;
  }

}
