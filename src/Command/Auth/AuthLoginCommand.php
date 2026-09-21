<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use SelfUpdate\SelfUpdateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:login', description: 'Register Cloud Platform API credentials', aliases: ['login'])]
final class AuthLoginCommand extends CommandBase
{
    private const DEVICE_CLIENT_ID = '';
    private const OKTA_DOMAIN = '';
    private const OKTA_AUTH_SERVER_ID = '';

    public function __construct(
        public LocalMachineHelper $localMachineHelper,
        protected CloudDataStore $datastoreCloud,
        protected AcquiaCliDatastore $datastoreAcli,
        protected ApiCredentialsInterface $cloudCredentials,
        protected TelemetryHelper $telemetryHelper,
        protected string $projectDir,
        protected ClientService $cloudApiClientService,
        public SshHelper $sshHelper,
        protected string $sshDir,
        LoggerInterface $logger,
        public SelfUpdateManager $selfUpdateManager,
        private GuzzleClient $httpClient = new GuzzleClient(['timeout' => 30]),
    ) {
        parent::__construct($this->localMachineHelper, $this->datastoreCloud, $this->datastoreAcli, $this->cloudCredentials, $this->telemetryHelper, $this->projectDir, $this->cloudApiClientService, $this->sshHelper, $this->sshDir, $logger, $this->selfUpdateManager);
    }

    protected function configure(): void
    {
        $this
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Your Cloud Platform API key')
            ->addOption('secret', 's', InputOption::VALUE_REQUIRED, 'Your Cloud Platform API secret')
            ->addOption('use-legacy-auth', null, InputOption::VALUE_NONE, 'Force API key/secret authentication instead of device code flow')
            ->addOption('environment', null, InputOption::VALUE_REQUIRED, 'Cloud Platform API environment', 'prod')
            ->setHelp('Authenticates ACLI with Acquia Cloud Platform. Uses API key/secret when credentials are already stored or passed via --key/--secret. Otherwise uses device code flow (no API key required).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Explicit legacy flag or key/secret passed on the command line — always legacy.
        if ($input->getOption('use-legacy-auth') || $input->getOption('key') || $input->getOption('secret')) {
            return $this->executeLegacyAuth($input, $output);
        }

        $keys = $this->datastoreCloud->get('keys');
        $activeKey = $this->datastoreCloud->get('acli_key');
        $deviceToken = $this->datastoreCloud->get('device_token');

        // Smart routing: existing API key → legacy, device token → device code re-auth.
        if ($activeKey && $keys) {
            $label = $keys[$activeKey]['label'] ?? $activeKey;
            $output->writeln("Already authenticated as <options=bold>$label</> (API key)");

            if ($input->isInteractive()) {
                $reauth = $this->io->confirm('Re-authenticate?', false);
                if (!$reauth) {
                    return Command::SUCCESS;
                }
            }
            return $this->executeLegacyAuth($input, $output);
        }

        if ($deviceToken) {
            $output->writeln('Already authenticated via <options=bold>device code</>');

            if ($input->isInteractive()) {
                $reauth = $this->io->confirm('Re-authenticate?', false);
                if (!$reauth) {
                    return Command::SUCCESS;
                }
            }
            return $this->executeDeviceCodeFlowWithFallback($input, $output);
        }

        // No stored credentials — try device code,
        // otherwise fall back to legacy key/secret prompt.
        if ($this->isDeviceCodeConfigured()) {
            return $this->executeDeviceCodeFlowWithFallback($input, $output);
        }

        return $this->executeLegacyAuth($input, $output);
    }

    private function executeDeviceCodeFlowWithFallback(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->executeDeviceCodeFlow($output);
        if (
            $result !== Command::SUCCESS
            && $input->isInteractive()
            && $this->io->confirm('Fall back to API key authentication?', false)
        ) {
            return $this->executeLegacyAuth($input, $output);
        }
        return $result;
    }

    private function isDeviceCodeConfigured(): bool
    {
        return (bool) (getenv('ACLI_DEVICE_CLIENT_ID') ?: self::DEVICE_CLIENT_ID)
            && (bool) (getenv('ACLI_OKTA_DOMAIN') ?: self::OKTA_DOMAIN)
            && (bool) (getenv('ACLI_OKTA_AUTH_SERVER_ID') ?: self::OKTA_AUTH_SERVER_ID);
    }

    private function executeDeviceCodeFlow(OutputInterface $output): int
    {
        $clientId   = getenv('ACLI_DEVICE_CLIENT_ID') ?: self::DEVICE_CLIENT_ID;
        $domain     = getenv('ACLI_OKTA_DOMAIN') ?: self::OKTA_DOMAIN;
        $authServer = getenv('ACLI_OKTA_AUTH_SERVER_ID') ?: self::OKTA_AUTH_SERVER_ID;

        $baseUrl = sprintf('https://%s/oauth2/%s/v1', $domain, $authServer);

        // Step 1 — initiate: get device_code + user_code.
        try {
            $response = $this->httpClient->post("$baseUrl/device/authorize", [
                'form_params' => [
                    'client_id' => $clientId,
                    'scope'     => 'openid profile email offline_access',
                ],
            ]);
        } catch (ClientException $e) {
            $output->writeln('<error>Failed to initiate device code flow: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $data = json_decode((string) $response->getBody(), true);

        $deviceCode       = $data['device_code'];
        $userCode         = $data['user_code'];
        $verifyUrl        = $data['verification_uri'];
        $verifyUrlComplete = $data['verification_uri_complete'] ?? $verifyUrl;
        $expiresIn        = $data['expires_in'] ?? 600;
        $interval         = $data['interval'] ?? 5;

        // Step 2 — surface the code and URL for the human.
        $output->writeln('');
        $output->writeln('Sign in to Acquia ID in your browser:');
        $output->writeln('');
        $output->writeln("  <href=$verifyUrl>$verifyUrl</>");
        $output->writeln('');
        $output->writeln('Then enter this code when prompted:');
        $output->writeln('');
        $output->writeln("  <options=bold>$userCode</>");
        $output->writeln('');

        if (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && $this->io->confirm('Do you want to open this page to sign in now?')) {
            $this->localMachineHelper->startBrowser($verifyUrlComplete);
        }

        $output->writeln(sprintf('Waiting for authorization... (code expires in %d minutes)', (int) ceil($expiresIn / 60)));

        // Step 3 — poll until approved, denied, or expired.
        $deadline = time() + $expiresIn;
        while (time() < $deadline) {
            sleep($interval);

            try {
                $tokenResponse = $this->httpClient->post("$baseUrl/token", [
                    'form_params' => [
                        'client_id'   => $clientId,
                        'device_code' => $deviceCode,
                        'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
                    ],
                ]);
                $token = json_decode((string) $tokenResponse->getBody(), true);
            } catch (ClientException $e) {
                // HTTP 4xx — parse the structured error body.
                $token = json_decode((string) $e->getResponse()->getBody(), true);
            } catch (ConnectException | RequestException $e) {
                // Transport error (DNS, timeout, no response). Back off exponentially
                // and retry; only abort when the device code itself has expired.
                $interval = min($interval * 2, 30);
                continue;
            }

            $error = $token['error'] ?? '';

            switch ($error) {
                case '':
                    // Success — store token and exit.
                    $this->storeDeviceToken($token, $clientId);
                    $output->writeln('');
                    $output->writeln('<info>✓ Authenticated successfully.</info>');
                    return Command::SUCCESS;

                case 'authorization_pending':
                    // Normal — human hasn't approved yet. Keep polling.
                    break;

                case 'slow_down':
                    // Okta asked us to back off.
                    $interval += 5;
                    break;

                case 'access_denied':
                    $output->writeln('<error>Authorization denied.</error>');
                    return Command::FAILURE;

                case 'expired_token':
                    $output->writeln('<error>Code expired. Run acli login again.</error>');
                    return Command::FAILURE;

                default:
                    $output->writeln("<error>Unexpected error: $error</error>");
                    return Command::FAILURE;
            }
        }

        $output->writeln('<error>Timed out waiting for authorization.</error>');
        return Command::FAILURE;
    }

    private function storeDeviceToken(array $token, string $clientId): void
    {
        $expiry = isset($token['expires_in'])
            ? time() + (int) $token['expires_in']
            : 0;

        $this->datastoreCloud->set('device_token', [
            'access_token'  => $token['access_token'],
            'client_id'     => $clientId,
            'expiry'        => $expiry,
            'refresh_token' => $token['refresh_token'] ?? null,
        ]);
    }

    private function executeLegacyAuth(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('environment') ?? 'prod';
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
