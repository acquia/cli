<?php

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AuthLoginCommand.
 */
class AuthLoginCommand extends CommandBase {

  protected static $defaultName = 'auth:login';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Register your Cloud API key and secret to use API functionality')
      ->setAliases(['login'])
      ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Your Cloud API key')
      ->addOption('secret', 's', InputOption::VALUE_REQUIRED, 'Your Cloud API secret');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($this->cloudApiClientService->isMachineAuthenticated()) {
      $answer = $this->io->confirm('Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?');
      if (!$answer) {
        return 0;
      }
    }

    // If keys already are saved locally, prompt to select.
    if ($input->isInteractive() && $keys = $this->datastoreCloud->get('keys')) {
      foreach ($keys as $uuid => $key) {
        $keys[$uuid]['uuid'] = $uuid;
      }
      $keys['create_new'] = [
        'uuid' => 'create_new',
        'label' => 'Enter a new API key',
      ];
      $selected_key = $this->promptChooseFromObjectsOrArrays($keys, 'uuid', 'label', 'Choose which API key to use');
      if ($selected_key['uuid'] !== 'create_new') {
        $this->datastoreCloud->set('acli_key', $selected_key['uuid']);
        $output->writeln("<info>Acquia CLI will use the API Key <options=bold>{$selected_key['label']}</></info>");
        $this->reAuthenticate($this->cloudCredentials->getCloudKey(), $this->cloudCredentials->getCloudSecret(), $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
        return 0;
      }
    }

    $this->promptOpenBrowserToCreateToken($input);
    $api_key = $this->determineApiKey($input);
    $api_secret = $this->determineApiSecret($input);
    $this->reAuthenticate($api_key, $api_secret, $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
    $this->writeApiCredentialsToDisk($api_key, $api_secret);
    $output->writeln("<info>Saved credentials</info>");

    return 0;
  }

  private function writeApiCredentialsToDisk(string $api_key, string $api_secret): void {
    $token_info = $this->cloudApiClientService->getClient()->request('get', "/account/tokens/{$api_key}");
    $keys = $this->datastoreCloud->get('keys');
    $keys[$api_key] = [
      'label' => $token_info->label,
      'uuid' => $api_key,
      'secret' => $api_secret,
    ];
    $this->datastoreCloud->set('keys', $keys);
    $this->datastoreCloud->set('acli_key', $api_key);
  }

}
