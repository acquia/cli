<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\CloudApi\DeviceTokenRefresher;
use Acquia\Cli\CloudApi\DeviceTokenRefreshResult;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use loophp\phposinfo\OsInfo;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use ReflectionProperty;
use Symfony\Component\Filesystem\Path;

class AccessTokenConnectorTest extends TestBase
{
    private static string $accessToken = 'testaccesstoken';

    protected function tearDown(): void
    {
        parent::tearDown();
        self::unsetAccessTokenEnvVars();
    }

    public static function setAccessTokenEnvVars(mixed $expired = false): void
    {
        if ($expired) {
            $accessTokenExpiry = time() - 300;
        } else {
            $accessTokenExpiry = time() + 300;
        }
        putenv('ACLI_ACCESS_TOKEN=' . self::$accessToken);
        putenv('ACLI_ACCESS_TOKEN_EXPIRY=' . $accessTokenExpiry);
    }

    public static function unsetAccessTokenEnvVars(): void
    {
        putenv('ACLI_ACCESS_TOKEN');
        putenv('ACLI_ACCESS_TOKEN_EXPIRY');
        putenv('ACLI_ACCESS_TOKEN_FILE');
        putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE');
    }

    public function testAccessToken(): void
    {
        self::setAccessTokenEnvVars();
        // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
        self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
                'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
                'key' => null,
                'secret' => null,
            ]
        );
        $connector = $connectorFactory->createConnector();
        self::assertInstanceOf(AccessTokenConnector::class, $connector);
        self::assertEquals(self::$accessToken, $connector->getAccessToken()
            ->getToken());

        $verb = 'get';
        $path = 'api';

        // Make sure that new access tokens are fetched using the refresh token.
        $mockProvider = $this->prophet->prophesize(GenericProvider::class);
        $mockProvider->getAuthenticatedRequest($verb, ConnectorInterface::BASE_URI . $path, Argument::type(AccessTokenInterface::class))
            ->willReturn($this->prophet->prophesize(RequestInterface::class)
                ->reveal())
            ->shouldBeCalled();
        $connector->setProvider($mockProvider->reveal());
        $connector->createRequest($verb, $path);

        $this->prophet->checkPredictions();
    }

    public function testTokenFile(): void
    {
        $accessTokenExpiry = time() + 300;
        $directory = [
            'expiry' => (string) $accessTokenExpiry . "\n",
            'token' => self::$accessToken . "\n",
        ];
        $vfs = vfsStream::setup('root', null, $directory);
        $tokenFile = Path::join($vfs->url(), 'token');
        $expiryFile = Path::join($vfs->url(), 'expiry');
        putenv('ACLI_ACCESS_TOKEN_FILE=' . $tokenFile);
        putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiryFile);
        self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
        self::assertEquals($accessTokenExpiry, $this->cloudCredentials->getCloudAccessTokenExpiry());
    }

    public function testMissingTokenFile(): void
    {
        $accessTokenExpiry = time() + 300;
        $directory = [
            'expiry' => (string) $accessTokenExpiry,
        ];
        $vfs = vfsStream::setup('root', null, $directory);
        $tokenFile = Path::join($vfs->url(), 'token');
        $expiryFile = Path::join($vfs->url(), 'expiry');
        putenv('ACLI_ACCESS_TOKEN_FILE=' . $tokenFile);
        putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiryFile);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Access token file not found at ' . $tokenFile);
        $this->cloudCredentials->getCloudAccessToken();
    }

    public function testMissingExpiryFile(): void
    {
        $directory = [
            'token' => self::$accessToken,
        ];
        $vfs = vfsStream::setup('root', null, $directory);
        $tokenFile = Path::join($vfs->url(), 'token');
        $expiryFile = Path::join($vfs->url(), 'expiry');
        putenv('ACLI_ACCESS_TOKEN_FILE=' . $tokenFile);
        putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiryFile);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Access token expiry file not found at ' . $expiryFile);
        $this->cloudCredentials->getCloudAccessTokenExpiry();
    }

    /**
     * Validate that if both an access token and API key/secret pair are
     * present, the pair is used.
     */
    public function testConnector(): void
    {
        self::setAccessTokenEnvVars();
        // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
        self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
                'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
                'key' => $this->cloudCredentials->getCloudKey(),
                'secret' => $this->cloudCredentials->getCloudSecret(),
            ]
        );
        $connector = $connectorFactory->createConnector();
        self::assertInstanceOf(Connector::class, $connector);
    }

    public function testExpiredAccessToken(): void
    {
        self::setAccessTokenEnvVars(true);
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
                'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
                'key' => null,
                'secret' => null,
            ]
        );
        $connector = $connectorFactory->createConnector();
        self::assertInstanceOf(Connector::class, $connector);
    }

    public function testConnectorConfig(): void
    {
        self::setAccessTokenEnvVars();
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => null,
                'key' => $this->cloudCredentials->getCloudKey(),
                'secret' => $this->cloudCredentials->getCloudSecret(),
            ]
        );
        // The telemetry ID is only sent when the developer opted in.
        $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, true);
        $clientService = new ClientService($connectorFactory, $this->application, $this->cloudCredentials, $this->datastoreCloud);
        $client = $clientService->getClient();
        $options = $client->getOptions();
        $this->assertStringStartsWith(
            sprintf('Acquia CLI (UNKNOWN, %s', OsInfo::uuid()),
            $options['headers']['User-Agent'][0],
        );

        $this->prophet->checkPredictions();
    }

    /**
     * The machine ID is resolved once and reused.
     *
     * OsInfo::uuid() shells out, and the User-Agent above is rebuilt on every
     * getClient() call, so repeated reads must not each spawn a subshell. The
     * assertions above cannot show this: they compare against OsInfo::uuid(),
     * which is the same value whether it was memoised or resolved again.
     */
    public function testTheMachineIdIsResolvedOnlyOnce(): void
    {
        $first = TelemetryHelper::getMachineId();
        $this->assertSame(OsInfo::uuid(), $first);

        $machineId = new ReflectionProperty(TelemetryHelper::class, 'machineId');
        try {
            $machineId->setValue(null, 'sentinel-not-read-from-this-machine');
            $this->assertSame('sentinel-not-read-from-this-machine', TelemetryHelper::getMachineId());
        } finally {
            // Restored, because the User-Agent assertions expect the real one.
            $machineId->setValue(null, $first);
        }
    }

    public function testDeviceTokenRefreshOnRequest(): void
    {
        $refresherProphecy = $this->prophet->prophesize(DeviceTokenRefresher::class);
        $refresherProphecy->getValidAccessToken()->willReturn(DeviceTokenRefreshResult::token('fresh-device-token'))->shouldBeCalled();

        $connector = new AccessTokenConnector([
            'access_token' => 'stale-token',
            'key' => null,
            'secret' => null,
        ], null, null, $refresherProphecy->reveal());

        $mockProvider = $this->prophet->prophesize(GenericProvider::class);
        $mockProvider->getAuthenticatedRequest('get', ConnectorInterface::BASE_URI . 'api', Argument::that(
            fn (AccessTokenInterface $t) => $t->getToken() === 'fresh-device-token'
        ))->willReturn($this->prophet->prophesize(RequestInterface::class)->reveal())->shouldBeCalled();
        $connector->setProvider($mockProvider->reveal());
        $request = $connector->createRequest('get', 'api');

        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->prophet->checkPredictions();
    }

    public function testDeviceTokenExpiredThrowsOnRequest(): void
    {
        $refresherProphecy = $this->prophet->prophesize(DeviceTokenRefresher::class);
        $refresherProphecy->getValidAccessToken()->willReturn(DeviceTokenRefreshResult::sessionEnded());

        $connector = new AccessTokenConnector([
            'access_token' => 'stale-token',
            'key' => null,
            'secret' => null,
        ], null, null, $refresherProphecy->reveal());

        $mockProvider = $this->prophet->prophesize(GenericProvider::class);
        $connector->setProvider($mockProvider->reveal());

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Device token session expired');
        $connector->createRequest('get', 'api');
    }

    /**
     * An unreachable server must not be reported as an expired session.
     *
     * The refresh token is still valid, so telling the developer to run
     * auth:login again costs them a browser round trip for nothing.
     */
    public function testUnreachableServerDoesNotClaimTheSessionExpired(): void
    {
        $refresherProphecy = $this->prophet->prophesize(DeviceTokenRefresher::class);
        $refresherProphecy->getValidAccessToken()->willReturn(DeviceTokenRefreshResult::serverUnreachable());

        $connector = new AccessTokenConnector([
            'access_token' => 'stale-token',
            'key' => null,
            'secret' => null,
        ], null, null, $refresherProphecy->reveal());

        $mockProvider = $this->prophet->prophesize(GenericProvider::class);
        $connector->setProvider($mockProvider->reveal());

        try {
            $connector->createRequest('get', 'api');
            $this->fail('Expected an exception for an unreachable authorization server.');
        } catch (AcquiaCliException $e) {
            $this->assertStringContainsString("Couldn't reach the authorization server", $e->getMessage());
            $this->assertStringNotContainsString('session expired', $e->getMessage());
            $this->assertStringNotContainsString('auth:login', $e->getMessage());
        }
    }

    public function testIdeHeader(): void
    {
        self::setAccessTokenEnvVars();
        IdeHelper::setCloudIdeEnvVars();
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => null,
                'key' => $this->cloudCredentials->getCloudKey(),
                'secret' => $this->cloudCredentials->getCloudSecret(),
            ]
        );
        // The telemetry ID is only sent when the developer opted in.
        $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, true);
        $clientService = new ClientService($connectorFactory, $this->application, $this->cloudCredentials, $this->datastoreCloud);
        $client = $clientService->getClient();
        $options = $client->getOptions();
        $this->assertEquals([
            'User-Agent' => [0 => sprintf('Acquia CLI (UNKNOWN, %s, acquia)', OsInfo::uuid())],
            'X-Cloud-IDE-UUID' => IdeHelper::$remoteIdeUuid,
        ], $options['headers']);

        $this->prophet->checkPredictions();
        IdeHelper::unsetCloudIdeEnvVars();
    }
}
