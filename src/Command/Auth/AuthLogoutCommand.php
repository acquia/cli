<?php

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\ApiCommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AuthLogoutCommand.
 */
class AuthLogoutCommand extends ApiCommandBase {

  protected static $defaultName = 'auth:logout';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Remove Cloud API key and secret from local machine.')
      ->setAliases(['logout']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    /** @var \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore */
    if ($this->cloudApiClientService->isMachineAuthenticated($this->datastoreCloud)) {
      $answer = $this->io->confirm('Are you sure you\'d like to unset the Acquia Cloud API key for Acquia CLI?');
      if (!$answer) {
        return 0;
      }
    }
    $this->datastoreCloud->remove('acli_key');

    $output->writeln("Unset the Acquia Cloud API key for Acquia CLI in <options=bold>{$this->cloudConfigFilepath}</></info>");

    return 0;
  }

}
