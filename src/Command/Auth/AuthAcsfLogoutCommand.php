<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:acsf-logout')]
class AuthAcsfLogoutCommand extends CommandBase {

  protected function configure(): void {
    $this->setDescription('Remove your Site Factory key and secret from your local machine.');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $factories = $this->datastoreCloud->get('acsf_factories');
    if (empty($factories)) {
      $this->io->error(['You are not logged into any factories.']);
      return Command::FAILURE;
    }
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
