<?php

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AuthLogoutCommand.
 */
class AuthLogoutCommand extends CommandBase {

  protected static $defaultName = 'auth:logout';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Remove Cloud API key and secret from local machine.')
      ->setAliases(['logout']);
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Acquia\Cli\DataStore\CloudDataStore $cloud_datastore */
    if ($this->cloudApiClientService->isMachineAuthenticated()) {
      $answer = $this->io->confirm('Are you sure you\'d like to unset the Acquia Cloud API key for Acquia CLI?');
      if (!$answer) {
        return 0;
      }
    }
    $this->datastoreCloud->remove('acli_key');

    $output->writeln("Unset the Acquia Cloud API key for Acquia CLI</info>");

    return 0;
  }

}
