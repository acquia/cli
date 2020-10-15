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
    if (CommandBase::isMachineAuthenticated($this->datastoreCloud)) {
      $answer = $this->io->confirm('Are you sure you\'d like to remove your Cloud Platform API login credentials from this machine?');
      if (!$answer) {
        return 0;
      }
    }
    $this->datastoreCloud->set('key', NULL);
    $this->datastoreCloud->set('secret', NULL);

    $output->writeln("Removed Cloud API credentials from <options=bold>{$this->cloudConfigFilepath}</></info>");

    return 0;
  }

}
