<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AuthLogoutCommand extends CommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'auth:logout';

  protected function configure(): void {
    $this->setDescription('Remove Cloud API key and secret from local machine.')
      ->setAliases(['logout']);
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

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
