<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AcsfApiAuthLoginCommand extends AcsfCommandBase {

  protected static $defaultName = 'auth:acsf-login';

  protected function configure(): void {
    $this->setDescription('Register your Site Factory API key and secret to use API functionality')
      ->addOption('username', 'u', InputOption::VALUE_REQUIRED, "Your Site Factory username")
      ->addOption('key', 'k', InputOption::VALUE_REQUIRED, "Your Site Factory key")
      ->addOption('factory-url', 'f', InputOption::VALUE_REQUIRED, "Your Site Factory URL");
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($input->getOption('factory-url')) {
      $factory_url = $input->getOption('factory-url');
    }
    elseif ($input->isInteractive() && $this->datastoreCloud->get('acsf_factories')) {
      $factories = $this->datastoreCloud->get('acsf_factories');
      $factory_choices = $factories;
      foreach ($factory_choices as $url => $factory_choice) {
        $factory_choices[$url]['url'] = $url;
      }
      $factory_choices['add_new'] = [
        'url' => 'Enter a new factory URL',
      ];
      $factory = $this->promptChooseFromObjectsOrArrays($factory_choices, 'url', 'url', 'Choose a Factory to login to');
      if ($factory['url'] === 'Enter a new factory URL') {
        $factory_url = $this->io->ask('Enter the full URL of the factory');
        $factory = [
          'url' => $factory_url,
          'users' => [],
        ];
      }
      else {
        $factory_url = $factory['url'];
      }

      $users = $factory['users'];
      $users['add_new'] = [
        'username' => 'Enter a new user',
      ];
      $selected_user = $this->promptChooseFromObjectsOrArrays($users, 'username', 'username', 'Choose which user to login as');
      if ($selected_user['username'] !== 'Enter a new user') {
        $this->datastoreCloud->set('acsf_active_factory', $factory_url);
        $factories[$factory_url]['active_user'] = $selected_user['username'];
        $this->datastoreCloud->set('acsf_factories', $factories);
        $output->writeln([
          "<info>Acquia CLI is now logged in to <options=bold>{$factory['url']}</> as <options=bold>{$selected_user['username']}</></info>",
        ]);
        return 0;
      }
    }
    else {
      $factory_url = $this->determineOption('factory-url');
    }

    $username = $this->determineOption('username');
    $key = $this->determineOption('key', TRUE);

    $this->writeAcsfCredentialsToDisk($factory_url, $username, $key);
    $output->writeln("<info>Saved credentials</info>");

    return 0;
  }

  private function writeAcsfCredentialsToDisk(?string $factory_url, string $username, string $key): void {
    $keys = $this->datastoreCloud->get('acsf_factories');
    $keys[$factory_url]['users'][$username] = [
      'key' => $key,
      'username' => $username,
    ];
    $keys[$factory_url]['url'] = $factory_url;
    $keys[$factory_url]['active_user'] = $username;
    $this->datastoreCloud->set('acsf_factories', $keys);
    $this->datastoreCloud->set('acsf_active_factory', $factory_url);
  }

}
