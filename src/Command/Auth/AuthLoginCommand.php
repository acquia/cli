<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Account;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:login', description: 'Register Cloud Platform API credentials', aliases: ['login'])]
final class AuthLoginCommand extends CommandBase {

  protected function configure(): void {
    $this
      ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Your Cloud Platform API key')
      ->addOption('secret', 's', InputOption::VALUE_REQUIRED, 'Your Cloud Platform API secret')
      ->setHelp('Acquia CLI can store multiple sets of credentials in case you have multiple Cloud Platform accounts. However, only a single account can be active at a time. This command allows you to activate a new or existing set of credentials.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $keys = $this->datastoreCloud->get('keys');
    $activeKey = $this->datastoreCloud->get('acli_key');
    if (is_array($keys) && !empty($keys) && !array_key_exists($activeKey, $keys)) {
      throw new AcquiaCliException('Invalid key in Cloud datastore; run acli auth:logout && acli auth:login to fix');
    }
    if ($activeKey) {
      $activeKeyLabel = $keys[$activeKey]['label'];
      $output->writeln("The following Cloud Platform API key is active: <options=bold>$activeKeyLabel</>");
    }
    else {
      $output->writeln('No Cloud Platform API key is active');
    }

    // If keys already are saved locally, prompt to select.
    if ($keys && $input->isInteractive()) {
      foreach ($keys as $uuid => $key) {
        $keys[$uuid]['uuid'] = $uuid;
      }
      $keys['create_new'] = [
        'label' => 'Enter a new API key',
        'uuid' => 'create_new',
      ];
      $selectedKey = $this->promptChooseFromObjectsOrArrays($keys, 'uuid', 'label', 'Activate a Cloud Platform API key');
      if ($selectedKey['uuid'] !== 'create_new') {
        $this->datastoreCloud->set('acli_key', $selectedKey['uuid']);
        $output->writeln("<info>Acquia CLI will use the API key <options=bold>{$selectedKey['label']}</></info>");
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
    $account = new Account($this->cloudApiClientService->getClient());
    $accountInfo = $account->get();
    $keys = $this->datastoreCloud->get('keys');
    $keys[$apiKey] = [
      'label' => $accountInfo->mail,
      'secret' => $apiSecret,
      'uuid' => $apiKey,
    ];
    $this->datastoreCloud->set('keys', $keys);
    $this->datastoreCloud->set('acli_key', $apiKey);
  }

}
