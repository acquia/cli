<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AcsfApiAuthLogoutCommand extends AcsfCommandBase {

  protected static $defaultName = 'auth:acsf-logout';

  protected function configure(): void {
    $this->setDescription('Remove your Site Factory key and secret from your local machine.');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$this->cloudApiClientService->isMachineAuthenticated()) {
      $this->io->error(['You are not logged into any factories.']);
      return 1;
    }
    $factories = $this->datastoreCloud->get('acsf_factories');
    foreach ($factories as $url => $factory) {
      $factories[$url]['url'] = $url;
    }
    $factory = $this->promptChooseFromObjectsOrArrays($factories, 'url', 'url', 'Choose a Factory to logout of');
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
