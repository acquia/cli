<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AcsfApiAuthLogoutCommand extends AcsfCommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'auth:acsf-logout';

  protected function configure(): void {
    $this->setDescription('Remove your Site Factory key and secret from your local machine.');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

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
    $factoryUrl = $factory['url'];

    /** @var \Acquia\Cli\AcsfApi\AcsfCredentials $cloudCredentials */
    $cloudCredentials = $this->cloudCredentials;
    $activeUser = $cloudCredentials->getFactoryActiveUser($factory);
    // @todo Only show factories the user is logged into.
    if (!$activeUser) {
      $this->io->error("You're already logged out of $factoryUrl");
      return 1;
    }
    $answer = $this->io->confirm("Are you sure you'd like to logout the user {$activeUser['username']} from $factoryUrl?");
    if (!$answer) {
      return Command::SUCCESS;
    }
    $factories[$factoryUrl]['active_user'] = NULL;
    $this->datastoreCloud->set('acsf_factories', $factories);
    $this->datastoreCloud->remove('acsf_active_factory');

    $output->writeln("Logged {$activeUser['username']} out of $factoryUrl</info>");

    return Command::SUCCESS;
  }

}
