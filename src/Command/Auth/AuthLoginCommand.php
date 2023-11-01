<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:login')]
class AuthLoginCommand extends CommandBase {

  protected function configure(): void {
    $this->setDescription('Register your Cloud API key and secret to use API functionality')
      ->setAliases(['login'])
      ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Your Cloud API key')
      ->addOption('secret', 's', InputOption::VALUE_REQUIRED, 'Your Cloud API secret');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($this->cloudApiClientService->isMachineAuthenticated()) {
      $answer = $this->io->confirm('Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?');
      if (!$answer) {
        return Command::SUCCESS;
      }
    }

    // If keys already are saved locally, prompt to select.
    if ($input->isInteractive() && $keys = $this->datastoreCloud->get('keys')) {
      foreach ($keys as $uuid => $key) {
        $keys[$uuid]['uuid'] = $uuid;
      }
      $keys['create_new'] = [
        'label' => 'Enter a new API key',
        'uuid' => 'create_new',
      ];
      $selectedKey = $this->promptChooseFromObjectsOrArrays($keys, 'uuid', 'label', 'Choose which API key to use');
      if ($selectedKey['uuid'] !== 'create_new') {
        $this->datastoreCloud->set('acli_key', $selectedKey['uuid']);
        $output->writeln("<info>Acquia CLI will use the API Key <options=bold>{$selectedKey['label']}</></info>");
        $this->reAuthenticate($this->cloudCredentials->getCloudKey(), $this->cloudCredentials->getCloudSecret(), $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
        return Command::SUCCESS;
      }
    }

    $this->promptOpenBrowserToCreateToken($input);
    $apiKey = $this->determineApiKey();
    $apiSecret = $this->determineApiSecret();
    $this->reAuthenticate($apiKey, $apiSecret, $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
    $this->writeApiCredentialsToDisk($apiKey, $apiSecret);
    $output->writeln("<info>Saved credentials</info>");

    return Command::SUCCESS;
  }

  private function writeApiCredentialsToDisk(string $apiKey, string $apiSecret): void {
    $tokenInfo = $this->cloudApiClientService->getClient()->request('get', "/account/tokens/{$apiKey}");
    $keys = $this->datastoreCloud->get('keys');
    $keys[$apiKey] = [
      'label' => $tokenInfo->label,
      'secret' => $apiSecret,
      'uuid' => $apiKey,
    ];
    $this->datastoreCloud->set('keys', $keys);
    $this->datastoreCloud->set('acli_key', $apiKey);
  }

}
