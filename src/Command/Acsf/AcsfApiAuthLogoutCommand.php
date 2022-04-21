<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AcsfLoginCommand.
 */
class AcsfApiAuthLogoutCommand extends AcsfCommandBase {

  protected static $defaultName = 'acsf:auth:logout';

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
    if (!$this->cloudApiClientService->isMachineAuthenticated($this->datastoreCloud)) {
      $this->io->error(['You are not logged into any factories.']);
      return 1;
    }
    $factories = $this->datastoreCloud->get('acsf_factories');
    $factory = $this->promptChooseFromObjectsOrArrays($factories, 'url', 'url', 'Please choose a Factory to logout of');
    $factory_url = $factory['url'];

    /** @var \Acquia\Cli\AcsfApi\AcsfCredentials $cloud_credentials */
    $cloud_credentials = $this->cloudCredentials;
    $active_user = $cloud_credentials->getFactoryActiveUser($factory);
    // @todo Only show factories the user is logged into.
    if (!$active_user) {
      $this->io->error("You're already logged out of $factory_url");
      return 1;
    }
    $answer = $this->io->confirm("Are you sure you'd like to logout the user {$active_user['username']} from $factory_url?");
    if (!$answer) {
      return 0;
    }
    $factories[$factory_url]['active_user'] = NULL;
    $this->datastoreCloud->set('acsf_factories', $factories);
    $this->datastoreCloud->remove('acsf_active_factory');

    $output->writeln("Logged {$active_user['username']} out of $factory_url</info>");

    return 0;
  }

}