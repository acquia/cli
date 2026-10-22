<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AuthConfig;
use Acquia\Cli\CloudApi\DeviceTokenRefresher;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Path;
use Throwable;

/**
 * Uses a real on-disk CloudDataStore rather than a double.
 */
#[Group('serial')]
class DeviceTokenRefresherTest extends TestBase
{
    /** @var array<string, string|false> */
    private array $savedEnvVars = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ACLI_AUTH_DOMAIN', 'ACLI_AUTH_SERVER_ID'] as $var) {
            $this->savedEnvVars[$var] = getenv($var);
            putenv($var);
        }
        $this->removeMockCloudConfigFile();
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnvVars as $var => $value) {
            putenv($value === false ? $var : $var . '=' . $value);
        }
        parent::tearDown();
    }

    private function givenAuthIsConfigured(): void
    {
        putenv('ACLI_AUTH_DOMAIN=example.acquia.com');
        putenv('ACLI_AUTH_SERVER_ID=ausTest');
    }

    /**
     * Writes the credential file.
     *
     * Call before createRefresher() to seed what its datastore loads; call after
     * to simulate another invocation writing while this one holds a stale
     * in-memory snapshot.
     *
     * @param array<string, mixed> $config
     */
    private function writeCloudConfig(array $config): void
    {
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode($config + ['send_telemetry' => false]));
    }

    /**
     * @return array<string, mixed>
     */
    private function readCloudConfig(): array
    {
        return json_decode(file_get_contents($this->cloudConfigFilepath), true);
    }

    /**
     * @param array<mixed> $responses
     */
    private function createRefresher(array $responses = [], ?callable $handler = null): DeviceTokenRefresher
    {
        $client = new GuzzleClient([
            'handler' => $handler ?? HandlerStack::create(new MockHandler($responses)),
        ]);

        return new DeviceTokenRefresher(
            new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath),
            $this->logger,
            $client,
            new AuthConfig(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function expiredToken(): array
    {
        return [
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
            'refresh_token' => 'old-refresh-token',
        ];
    }

    public function testReturnsNullWhenNoTokenStored(): void
    {
        $this->writeCloudConfig([]);

        $this->assertNull($this->createRefresher()->getValidAccessToken()->accessToken);
    }

    public function testReturnsStoredTokenWhenNotExpired(): void
    {
        $this->writeCloudConfig(['device_token' => [
            'access_token' => 'valid-token',
            'client_id' => 'client-123',
            'expiry' => time() + 300,
            'refresh_token' => 'refresh-123',
        ],
        ]);

        $this->assertSame('valid-token', $this->createRefresher()->getValidAccessToken()->accessToken);
    }

    public function testReturnsNullWhenExpiredAndRefreshTokenMissing(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => [
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
        ],
        ]);

        $this->assertNull($this->createRefresher()->getValidAccessToken()->accessToken);
    }

    public function testReturnsNullWhenExpiredAndAuthConfigMissing(): void
    {
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);

        $this->assertNull($this->createRefresher()->getValidAccessToken()->accessToken);
    }

    public function testRefreshesExpiredTokenAndPersistsIt(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
                'refresh_token' => 'new-refresh-token',
            ])),
        ]);

        $this->assertSame('new-access-token', $refresher->getValidAccessToken()->accessToken);

        $stored = $this->readCloudConfig()['device_token'];
        $this->assertSame('new-access-token', $stored['access_token']);
        $this->assertSame('new-refresh-token', $stored['refresh_token']);
        $this->assertSame('client-123', $stored['client_id']);
        $this->assertGreaterThan(time(), $stored['expiry']);
    }

    public function testRestrictsTheCredentialFileToTheOwner(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        chmod($this->cloudConfigFilepath, 0644);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['access_token' => 'new-access-token', 'expires_in' => 3600])),
        ]);

        $refresher->getValidAccessToken();

        clearstatcache();
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->cloudConfigFilepath)), -4));
        $this->assertSame('new-access-token', $this->readCloudConfig()['device_token']['access_token']);
    }

    public function testThrowsAndKeepsTheOldFileWhenTheRefreshCannotBeSaved(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['access_token' => 'new-access-token', 'expires_in' => 3600])),
        ]);
        chmod(dirname($this->cloudConfigFilepath), 0555);

        try {
            $refresher->getValidAccessToken();
            $this->fail('Expected the unsaveable refresh to throw.');
        } catch (IOException) {
            // Expected: the datastore lets filesystem errors propagate too.
        } finally {
            chmod(dirname($this->cloudConfigFilepath), 0755);
        }

        $this->assertSame('expired-token', $this->readCloudConfig()['device_token']['access_token']);
    }

    public function testWritesThroughASymlinkedCredentialFile(): void
    {
        $this->givenAuthIsConfigured();
        $dir = $this->getTempDir();
        $target = Path::join($dir, 'dotfiles', 'cloud_api.conf');
        $link = Path::join($dir, 'config', 'cloud_api.conf');
        $this->fs->dumpFile($target, json_encode(['device_token' => $this->expiredToken(), 'send_telemetry' => false]));
        $this->fs->mkdir(dirname($link));
        $this->fs->symlink($target, $link);

        try {
            $client = new GuzzleClient([
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(200, [], json_encode(['access_token' => 'new-access-token', 'expires_in' => 3600])),
                ])),
            ]);
            $datastore = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $link);
            (new DeviceTokenRefresher($datastore, $this->logger, $client, new AuthConfig()))->getValidAccessToken();

            $this->assertTrue(is_link($link));
            $this->assertSame('new-access-token', json_decode(file_get_contents($target), true)['device_token']['access_token']);
        } finally {
            $this->fs->remove($dir);
        }
    }

    public function testKeepsOldRefreshTokenWhenResponseOmitsNewOne(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['access_token' => 'new-access-token', 'expires_in' => 3600])),
        ]);

        $refresher->getValidAccessToken();

        $this->assertSame('old-refresh-token', $this->readCloudConfig()['device_token']['refresh_token']);
    }

    public function testUsesATokenAnotherInvocationWroteWhileThisOneWasStale(): void
    {
        // The datastore loads an expired token; another invocation then writes a
        // fresh one. Spending a rotation here would kill the session for both.
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(500, [], 'the refresher must not reach the authorization server'),
        ]);

        $this->writeCloudConfig(['device_token' => [
            'access_token' => 'written-by-another-process',
            'client_id' => 'client-123',
            'expiry' => time() + 3600,
            'refresh_token' => 'their-refresh-token',
        ],
        ]);

        $this->assertSame('written-by-another-process', $refresher->getValidAccessToken()->accessToken);
    }

    public function testDoesNotClobberAnotherProcessesRefreshWithALaterUnrelatedSet(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $datastore = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $client = new GuzzleClient([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(500, [], 'the refresher must not reach the authorization server'),
            ])),
        ]);
        $refresher = new DeviceTokenRefresher($datastore, $this->logger, $client, new AuthConfig());

        $this->writeCloudConfig(['device_token' => [
            'access_token' => 'written-by-another-process',
            'client_id' => 'client-123',
            'expiry' => time() + 3600,
            'refresh_token' => 'their-refresh-token',
        ],
        ]);

        $this->assertSame('written-by-another-process', $refresher->getValidAccessToken()->accessToken);

        $datastore->set('user', ['uuid' => 'test-uuid']);

        $stored = $this->readCloudConfig()['device_token'];
        $this->assertSame('written-by-another-process', $stored['access_token']);
        $this->assertSame('their-refresh-token', $stored['refresh_token']);
    }

    public function testDoesNotClobberCredentialsWrittenSinceTheProcessStarted(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['access_token' => 'new-access-token', 'expires_in' => 3600])),
        ]);

        $this->writeCloudConfig([
            'acli_key' => 'added-later',
            'device_token' => $this->expiredToken(),
            'keys' => ['added-later' => ['label' => 'Later Key', 'secret' => 's', 'uuid' => 'added-later']],
        ]);

        $refresher->getValidAccessToken();

        $config = $this->readCloudConfig();
        $this->assertSame('added-later', $config['acli_key']);
        $this->assertArrayHasKey('added-later', $config['keys']);
        $this->assertSame('new-access-token', $config['device_token']['access_token']);
    }

    public function testReturnsNullOnInvalidGrant(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(400, [], json_encode(['error' => 'invalid_grant'])),
        ]);

        $result = $refresher->getValidAccessToken();

        $this->assertNull($result->accessToken);
        $this->assertFalse($result->serverUnreachable, 'invalid_grant ends the session; it is not retryable.');
        $this->assertSame('expired-token', $this->readCloudConfig()['device_token']['access_token']);
    }

    public function testReturnsNullOnA400ThatIsNotInvalidGrant(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(400, [], json_encode(['error' => 'invalid_client'])),
        ]);

        $this->assertNull($refresher->getValidAccessToken()->accessToken);
    }

    /**
     * @return array<string, array{0: Response|Throwable}>
     */
    public static function retryableRefreshFailures(): array
    {
        return [
            'rate limited' => [new Response(429, [], json_encode(['error' => 'too_many_requests']))],
            'server error' => [new Response(503, [], 'service unavailable')],
            'transport failure' => [new ConnectException('cURL error 6: Could not resolve host', new Request('POST', 'https://example.acquia.com'))],
        ];
    }

    /**
     * None of these end the session, so none may be reported as one that has.
     */
    #[DataProvider('retryableRefreshFailures')]
    public function testARetryableFailureLeavesTheStoredTokenAlone(Response|Throwable $queued): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([$queued]);

        $result = $refresher->getValidAccessToken();

        $this->assertNull($result->accessToken);
        $this->assertTrue($result->serverUnreachable);
        $this->assertSame('expired-token', $this->readCloudConfig()['device_token']['access_token']);
    }

    public function testReturnsNullWhenRefreshResponseHasNoAccessToken(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['token_type' => 'Bearer'])),
        ]);

        $this->assertNull($refresher->getValidAccessToken()->accessToken);
    }

    public function testRefreshRequestCarriesAnExplicitTimeout(): void
    {
        // GuzzleHttp\Client is autowired, so the container injects one built
        // without options; a hung /token would block every API request.
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $captured = [];
        $refresher = $this->createRefresher([], function ($request, array $options) use (&$captured) {
            $captured[] = $options;
            return Create::promiseFor(new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ])));
        });

        $refresher->getValidAccessToken();

        $this->assertNotEmpty($captured);
        $this->assertArrayHasKey('timeout', $captured[0]);
        $this->assertGreaterThan(0, $captured[0]['timeout']);
    }

    public function testDoesNotClobberTheRefreshWithALaterUnrelatedSet(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $datastore = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $client = new GuzzleClient([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode([
                    'access_token' => 'new-access-token',
                    'expires_in' => 3600,
                    'refresh_token' => 'new-refresh-token',
                ])),
            ])),
        ]);
        $refresher = new DeviceTokenRefresher($datastore, $this->logger, $client, new AuthConfig());

        $this->assertSame('new-access-token', $refresher->getValidAccessToken()->accessToken);

        $datastore->set('user', ['uuid' => 'test-uuid']);

        $stored = $this->readCloudConfig()['device_token'];
        $this->assertSame('new-access-token', $stored['access_token']);
        $this->assertSame('new-refresh-token', $stored['refresh_token']);
    }

    public function testTakesTheLockBesideTheCredentialFile(): void
    {
        $this->givenAuthIsConfigured();
        $this->writeCloudConfig(['device_token' => $this->expiredToken()]);
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['access_token' => 'new-access-token', 'expires_in' => 3600])),
        ]);

        $refresher->getValidAccessToken();

        $this->assertFileExists($this->cloudConfigFilepath . '.lock');
    }
}
