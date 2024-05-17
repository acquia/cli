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

#[AsCommand(name: 'auth:logout', description: 'Remove Cloud Platform API credentials', aliases: ['logout'])]
final class AuthLogoutCommand extends CommandBase {

  protected function configure(): void {
    $this->addOption('delete', NULL, InputOption::VALUE_NEGATABLE, 'Delete the active Cloud Platform API credentials');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $keys = $this->datastoreCloud->get('keys');
    $activeKey = $this->datastoreCloud->get('acli_key');
    if (!$activeKey) {
      throw new AcquiaCliException('There is no active Cloud Platform API key');
    }
    $activeKeyLabel = $keys[$activeKey]['label'];
    $output->writeln("<info>The key <options=bold>$activeKeyLabel</> will be deactivated on this machine. However, the credentials will remain on disk and can be reactivated by running <options=bold>acli auth:login</> unless you also choose to delete them.</info>");
    $delete = $this->determineOption('delete', FALSE, NULL, NULL, FALSE);
    $this->datastoreCloud->remove('acli_key');
    $action = 'deactivated';
    if ($delete) {
      $this->datastoreCloud->remove("keys.$activeKey");
      $action = 'deleted';
    }
    $output->writeln("<info>The active Cloud Platform API credentials were $action</info>");
    $output->writeln('<info>No Cloud Platform API key is active. Run <options=bold>acli auth:login</> to continue using the Cloud Platform API.</info>');

    return Command::SUCCESS;
  }

}
