<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Connector;
use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property AuthLoginCommand $command
 */
class AuthLoginCommandTest extends CommandTestBase
{
    /** @var array<string, string|false> */
    private array $savedDeviceCodeEnvVars = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ACLI_DEVICE_CLIENT_ID', 'ACLI_OKTA_DOMAIN', 'ACLI_OKTA_AUTH_SERVER_ID'] as $var) {
            $this->savedDeviceCodeEnvVars[$var] = getenv($var);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->savedDeviceCodeEnvVars as $var => $value) {
            putenv($value === false ? $var : $var . '=' . $value);
        }
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AuthLoginCommand::class);
    }

    private function enableDeviceCodeConfig(): void
    {
        putenv('ACLI_DEVICE_CLIENT_ID=test-client-id');
        putenv('ACLI_OKTA_DOMAIN=example.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausTest');
    }

    /**
     * @param array<\GuzzleHttp\Psr7\Response> $responses
     */
    private function createDeviceCodeCommand(array $responses, ?ObjectProphecy $localMachineHelperProphecy = null): AuthLoginCommand
    {
        $mock = new MockHandler($responses);
        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $localMachineHelper = $localMachineHelperProphecy?->reveal()
            ?? $this->prophet->prophesize(LocalMachineHelper::class)->reveal();

        return new AuthLoginCommand(
            $localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->acliRepoRoot,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
            $client,
        );
    }

    private function givenFreshCloudConfigWithTelemetryDisabled(): void
    {
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode(['send_telemetry' => false]));
        $this->createDataStores();
    }

    private function deviceAuthorizeResponse(): Response
    {
        return new Response(200, [], json_encode([
            'device_code' => 'test-device-code',
            'expires_in' => 600,
            'interval' => 0,
            'user_code' => 'ABCD1234',
            'verification_uri' => 'https://example.okta.com/activate',
        ]));
    }

    // -------------------------------------------------------------------------
    // Device code flow tests
    // -------------------------------------------------------------------------
    public function testDeviceCodeFlowSuccess(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $this->command = $this->createDeviceCodeCommand([
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => 300,
                'refresh_token' => 'test-refresh-token',
            ])),
        ]);

        $this->executeCommand([], ['no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('https://example.okta.com/activate', $output);
        $this->assertStringContainsString('ABCD1234', $output);
        $this->assertStringContainsString('Authenticated successfully', $output);

        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $deviceToken = $config->get('device_token');
        $this->assertEquals('test-access-token', $deviceToken['access_token']);
        $this->assertEquals('test-refresh-token', $deviceToken['refresh_token']);
        $this->assertEquals('test-client-id', $deviceToken['client_id']);
    }

    public function testDeviceCodeFlowAuthorizationPendingThenSuccess(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $this->command = $this->createDeviceCodeCommand([
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode(['error' => 'authorization_pending'])),
            new Response(200, [], json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => 300,
                'refresh_token' => 'test-refresh-token',
            ])),
        ]);

        $this->executeCommand([], ['no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Authenticated successfully', $output);
    }

    public function testDeviceCodeFlowAccessDenied(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $this->command = $this->createDeviceCodeCommand([
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode(['error' => 'access_denied'])),
        ]);

        $this->executeCommand([], ['no', 'no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Authorization denied', $output);
    }

    public function testDeviceCodeFlowExpiredToken(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $this->command = $this->createDeviceCodeCommand([
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode(['error' => 'expired_token'])),
        ]);

        $this->executeCommand([], ['no', 'no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Code expired', $output);
    }

    public function testDeviceCodeFlowInitiateFailure(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $this->command = $this->createDeviceCodeCommand([
            new Response(400, [], json_encode(['error' => 'invalid_client'])),
        ]);

        $this->executeCommand([], ['no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Failed to initiate device code flow', $output);
    }

    public function testDeviceCodeFlowFallsBackToLegacyOnFailure(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);

        $localMachineHelperProphecy = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelperProphecy->useTty()->willReturn(false);
        $this->command = $this->createDeviceCodeCommand([
            new Response(400, [], json_encode(['error' => 'invalid_client'])),
        ], $localMachineHelperProphecy);

        // inputs: 'yes' = fallback confirm, 'no' = open browser to create token, then key + secret.
        $this->executeCommand([], ['yes', 'no', self::$key, self::$secret]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Failed to initiate device code flow', $output);
        $this->assertStringContainsString('Saved credentials', $output);
    }

    public function testDeviceCodeFlowOpensBrowserWhenConfirmed(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $localMachineHelperProphecy = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelperProphecy->startBrowser('https://example.okta.com/activate')
            ->shouldBeCalled()
            ->willReturn(true);
        $this->command = $this->createDeviceCodeCommand([
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => 300,
            ])),
        ], $localMachineHelperProphecy);

        $this->executeCommand([], ['yes']);
    }

    public function testDeviceCodeFlowDoesNotOpenBrowserWhenDeclined(): void
    {
        $this->enableDeviceCodeConfig();
        $this->givenFreshCloudConfigWithTelemetryDisabled();
        $localMachineHelperProphecy = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelperProphecy->startBrowser(Argument::any())
            ->shouldNotBeCalled();
        $this->command = $this->createDeviceCodeCommand([
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => 300,
            ])),
        ], $localMachineHelperProphecy);

        $this->executeCommand([], ['no']);
    }

    public function testSmartRoutingDeviceTokenReauthDeclined(): void
    {
        $this->enableDeviceCodeConfig();
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'device_token' => [
                'access_token' => 'existing-token',
                'client_id' => 'test-client-id',
                'expiry' => time() + 300,
                'refresh_token' => 'existing-refresh-token',
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $this->command = $this->createDeviceCodeCommand([]);

        $this->executeCommand([], ['no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Already authenticated via', $output);
        $this->assertStringNotContainsString('Authenticated successfully', $output);
    }

    // -------------------------------------------------------------------------
    // Legacy auth tests
    // -------------------------------------------------------------------------
    public function testAuthLoginCommand(): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $this->assertKeySavedCorrectly();
    }

    /**
     * @return string[]
     */
    public static function providerTestAuthLoginCommandWithStagingEnvironment(): array
    {
        return [
            ['staging'],
            ['Staging'],
            [' staging'],
        ];
    }

    #[DataProvider('providerTestAuthLoginCommandWithStagingEnvironment')]
    public function testAuthLoginCommandWithStagingEnvironment(string $environment): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--environment' => $environment,
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $this->assertKeySavedCorrectly();
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $keys = $config->get('keys');
        $this->assertSame('https://staging.cloud.acquia.com/api', $keys[self::$key]['cloud_api_base_uri']);
        $this->assertSame('https://staging.accounts.acquia.com/api/auth/oauth/token', $keys[self::$key]['accounts_uri']);
    }

    public function testAuthLoginCommandProdEnvironmentDoesNotStoreUris(): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $keys = $config->get('keys');
        $this->assertNull($keys[self::$key]['cloud_api_base_uri'] ?? null);
        $this->assertNull($keys[self::$key]['accounts_uri'] ?? null);
    }

    public function testAuthLoginExplicitProdEnvironmentDoesNotStoreUris(): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--environment' => 'prod',
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $keys = $config->get('keys');
        $this->assertNull($keys[self::$key]['cloud_api_base_uri'] ?? null);
        $this->assertNull($keys[self::$key]['accounts_uri'] ?? null);
    }

    public function testAuthLoginInteractiveSelectsExistingEnvironmentKey(): void
    {
        $stagingKeyUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'acli_key' => $stagingKeyUuid,
            'keys' => [
                $stagingKeyUuid => [
                    'accounts_uri' => 'https://staging.accounts.acquia.com/api/auth/oauth/token',
                    'cloud_api_base_uri' => 'https://staging.cloud.acquia.com/api',
                    'label' => 'Staging Key',
                    'secret' => self::$secret,
                    'uuid' => $stagingKeyUuid,
                ],
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $this->cloudCredentials = new CloudCredentials($this->datastoreCloud);
        $this->command = $this->createCommand();

        $this->executeCommand(
            ['--environment' => 'staging'],
            ['Staging Key'],
        );
        $output = $this->getDisplay();

        $this->assertStringContainsString('Acquia CLI will use the API key', $output);
        $this->assertStringContainsString('Staging Key', $output);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $this->assertSame($stagingKeyUuid, $config->get('acli_key'));
    }

    public function testAuthLoginInteractiveCreatesNewKeyForExistingEnvironment(): void
    {
        $stagingKeyUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'acli_key' => $stagingKeyUuid,
            'keys' => [
                $stagingKeyUuid => [
                    'accounts_uri' => 'https://staging.accounts.acquia.com/api/auth/oauth/token',
                    'cloud_api_base_uri' => 'https://staging.cloud.acquia.com/api',
                    'label' => 'Staging Key',
                    'secret' => self::$secret,
                    'uuid' => $stagingKeyUuid,
                ],
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand(
            ['--environment' => 'staging', '--key' => self::$key, '--secret' => self::$secret],
            ['Enter a new API key'],
        );
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $this->assertSame(self::$key, $config->get('acli_key'));
        $keys = $config->get('keys');
        $this->assertSame('https://staging.cloud.acquia.com/api', $keys[self::$key]['cloud_api_base_uri']);
        $this->assertSame('https://staging.accounts.acquia.com/api/auth/oauth/token', $keys[self::$key]['accounts_uri']);
    }

    public function testAuthLoginProdLoginSkipsNonProdKeys(): void
    {
        $stagingKeyUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        // Only a staging key exists; logging in to prod should not prompt for it.
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'acli_key' => $stagingKeyUuid,
            'keys' => [
                $stagingKeyUuid => [
                    'accounts_uri' => 'https://staging.accounts.acquia.com/api/auth/oauth/token',
                    'cloud_api_base_uri' => 'https://staging.cloud.acquia.com/api',
                    'label' => 'Staging Key',
                    'secret' => self::$secret,
                    'uuid' => $stagingKeyUuid,
                ],
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        // No interactive input needed: no prod keys exist so no selection prompt is shown.
        $this->executeCommand([
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $this->assertSame(self::$key, $config->get('acli_key'));
        $keys = $config->get('keys');
        $this->assertNull($keys[self::$key]['cloud_api_base_uri'] ?? null);
    }

    public function testAuthLoginNonInteractiveWithExistingProdKey(): void
    {
        $existingKeyUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'acli_key' => $existingKeyUuid,
            'keys' => [
                $existingKeyUuid => [
                    'label' => 'Existing Key',
                    'secret' => 'existing-secret',
                    'uuid' => $existingKeyUuid,
                    // No cloud_api_base_uri = prod key.
                ],
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand(
            ['--key' => self::$key, '--secret' => self::$secret],
            inputs: [],
            interactive: false,
        );
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $this->assertSame(self::$key, $config->get('acli_key'));
    }

    public function testAuthLoginNoKeysCommand(): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode(['send_telemetry' => false]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $this->assertKeySavedCorrectly();
    }

    public static function providerTestAuthLoginInvalidInputCommand(): Generator
    {
        yield
        [
            [],
            ['--key' => 'no spaces are allowed', '--secret' => self::$secret],
        ];
        yield
        [
            [],
            ['--key' => 'shorty', '--secret' => self::$secret],
        ];
        yield
        [
            [],
            ['--key' => ' ', '--secret' => self::$secret],
        ];
    }

    #[DataProvider('providerTestAuthLoginInvalidInputCommand')]
    public function testAuthLoginInvalidInputCommand(array $inputs, array $args): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();
        $this->expectException(ValidatorException::class);
        $this->executeCommand($args, $inputs);
    }

    public static function providerTestAuthLoginInvalidEnvironmentCommand(): Generator
    {
        yield [['--key' => self::$key, '--secret' => self::$secret, '--environment' => 'my env']];
        yield [['--key' => self::$key, '--secret' => self::$secret, '--environment' => 'env!']];
        yield [['--key' => self::$key, '--secret' => self::$secret, '--environment' => 'env_name']];
    }

    #[DataProvider('providerTestAuthLoginInvalidEnvironmentCommand')]
    public function testAuthLoginInvalidEnvironmentCommand(array $args): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Invalid environment value: ' . $args['--environment']);
        $this->executeCommand($args);
    }

    public function testAuthLoginInvalidDatastore(): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $data = [
            'acli_key' => 'key2',
            'keys' => [
                'key1' => [
                    'label' => 'foo',
                    'secret' => 'foo',
                    'uuid' => 'foo',
                ],
            ],
        ];
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode($data));
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage("Configuration file at the following path contains invalid keys: $this->cloudConfigFilepath Invalid configuration for path \"cloud_api\": acli_key must exist in keys");
        $this->createDataStores();
    }

    protected function assertInteractivePrompts(string $output): void
    {
        // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
        $this->assertStringContainsString('You will need a Cloud Platform API token from https://cloud.acquia.com/a/profile/tokens', $output);
        $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
        $this->assertStringContainsString('Enter your Cloud API key (option -k, --key):', $output);
        $this->assertStringContainsString('Enter your Cloud API secret (option -s, --secret) (input will be hidden):', $output);
    }

    protected function assertKeySavedCorrectly(): void
    {
        $credsFile = $this->cloudConfigFilepath;
        $this->assertFileExists($credsFile);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $credsFile);
        $this->assertTrue($config->exists('acli_key'));
        $this->assertEquals(self::$key, $config->get('acli_key'));
        $this->assertTrue($config->exists('keys'));
        $keys = $config->get('keys');
        $this->assertArrayHasKey(self::$key, $keys);
        $this->assertArrayHasKey('label', $keys[self::$key]);
        $this->assertArrayHasKey('secret', $keys[self::$key]);
        $this->assertEquals(self::$secret, $keys[self::$key]['secret']);
    }
}
