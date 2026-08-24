<?php

declare(strict_types=1);

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
final class AuthLoginCommand extends CommandBase
{
    protected function configure(): void
    {
        $this
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Your Cloud Platform API key')
            ->addOption('secret', 's', InputOption::VALUE_REQUIRED, 'Your Cloud Platform API secret')
            ->addOption('environment', null, InputOption::VALUE_REQUIRED, 'Cloud Platform API environment', 'prod')
            ->setHelp('Acquia CLI can store multiple sets of credentials in case you have multiple Cloud Platform accounts. However, only a single account can be active at a time. This command allows you to activate a new or existing set of credentials.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('environment');
        [$baseUri, $accountsUri] = $this->getUrisForEnvironment($env);

        $keys = $this->datastoreCloud->get('keys');
        $activeKey = $this->datastoreCloud->get('acli_key');
        if ($activeKey) {
            $activeKeyLabel = $keys[$activeKey]['label'];
            $output->writeln("The following Cloud Platform API key is active: <options=bold>$activeKeyLabel</>");
        } else {
            $output->writeln('No Cloud Platform API key is active');
        }

        $matchingKeys = array_filter(
            $keys ?? [],
            static fn(array $keyData) => ($keyData['cloud_api_base_uri'] ?? null) === $baseUri,
        );

        // If keys already are saved locally for this environment, prompt to select.
        if ($matchingKeys && $input->isInteractive()) {
            foreach ($matchingKeys as $uuid => $key) {
                $matchingKeys[$uuid]['uuid'] = $uuid;
            }
            $matchingKeys['create_new'] = [
                'label' => 'Enter a new API key',
                'uuid' => 'create_new',
            ];
            $selectedKey = $this->promptChooseFromObjectsOrArrays($matchingKeys, 'uuid', 'label', 'Activate a Cloud Platform API key');
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
        $this->reAuthenticate($apiKey, $apiSecret, $baseUri, $accountsUri);
        $this->writeApiCredentialsToDisk($apiKey, $apiSecret, $baseUri, $accountsUri);
        $output->writeln("<info>Saved credentials</info>");

        return Command::SUCCESS;
    }

    /**
     * @return array{?string, ?string}
     */
    private function getUrisForEnvironment(string $env): array
    {
        $env = strtolower(trim($env));
        if ($env === '' || $env === 'prod') {
            return [null, null];
        }
        if (!preg_match('/^[a-z0-9-]+$/', $env)) {
            throw new AcquiaCliException('Invalid environment value: {env}', ['env' => $env]);
        }
        return [
            "https://$env.cloud.acquia.com/api",
            "https://$env.accounts.acquia.com/api/auth/oauth/token",
        ];
    }

    private function writeApiCredentialsToDisk(string $apiKey, string $apiSecret, ?string $baseUri = null, ?string $accountsUri = null): void
    {
        $account = new Account($this->cloudApiClientService->getClient());
        $accountInfo = $account->get();
        $keys = $this->datastoreCloud->get('keys');
        $keyData = [
            'label' => $accountInfo->mail,
            'secret' => $apiSecret,
            'uuid' => $apiKey,
        ];
        if ($baseUri !== null) {
            $keyData['cloud_api_base_uri'] = $baseUri;
        }
        if ($accountsUri !== null) {
            $keyData['accounts_uri'] = $accountsUri;
        }
        $keys[$apiKey] = $keyData;
        $this->datastoreCloud->set('keys', $keys);
        $this->datastoreCloud->set('acli_key', $apiKey);
    }
}
