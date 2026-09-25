<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\CloudApi\PathRewriteConnector;
use AcquiaCloudApi\Connector\Connector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ConnectorFactory. Ensures that the factory returns the correct
 * connector type depending on the presence of the AH_CODEBASE_UUID environment variable.
 */
#[CoversClass(ConnectorFactory::class)]
class ConnectorFactoryTest extends TestCase
{
    /**
     * Stores the original value of AH_CODEBASE_UUID to restore after each test.
     */
    private string|false $originalEnv;

    /**
     * Saves the original environment variable before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = getenv('AH_CODEBASE_UUID');
    }


    #[DataProvider('connectorFactoryProvider')]
    public function testCreateConnectorFactoryBehavior(?string $envValue, string $expectedClass): void
    {
        if ($envValue !== null) {
            putenv("AH_CODEBASE_UUID=$envValue");
        } else {
            putenv('AH_CODEBASE_UUID');
        }
        $factory = new ConnectorFactory(['key' => 'k', 'secret' => 's'], 'https://api.example.com');
        $connector = $factory->createConnector();
        $this->assertInstanceOf($expectedClass, $connector);
    }

    public function testBuildsAccessTokenConnectorForAStaleDeviceTokenWithARefreshToken(): void
    {
        putenv('AH_CODEBASE_UUID');
        $factory = new ConnectorFactory([
            'accessToken' => null,
            'deviceAccessToken' => 'expired-but-present',
            'key' => null,
            'secret' => null,
        ], 'https://api.example.com');

        $this->assertInstanceOf(AccessTokenConnector::class, $factory->createConnector());
    }

    public function testStoredKeyAndSecretOutrankADeviceToken(): void
    {
        putenv('AH_CODEBASE_UUID');
        $factory = new ConnectorFactory([
            'accessToken' => null,
            'deviceAccessToken' => 'device-token',
            'key' => 'k',
            'secret' => 's',
        ], 'https://api.example.com');

        $connector = $factory->createConnector();

        $this->assertInstanceOf(Connector::class, $connector);
        $this->assertNotInstanceOf(AccessTokenConnector::class, $connector);
    }

    public function testDeviceTokenOutranksTheAccessTokenEnvVar(): void
    {
        putenv('AH_CODEBASE_UUID');
        // An expired env token makes the branches distinguishable: alone it fails the
        // expiry check, so an AccessTokenConnector here can only be the device path.
        $expiredEnvToken = [
            'accessToken' => 'env-access-token',
            'accessTokenExpiry' => time() - 300,
            'key' => null,
            'secret' => null,
        ];

        $withoutDeviceToken = (new ConnectorFactory($expiredEnvToken, 'https://api.example.com'))->createConnector();
        $this->assertNotInstanceOf(AccessTokenConnector::class, $withoutDeviceToken);

        $withDeviceToken = (new ConnectorFactory(
            $expiredEnvToken + ['deviceAccessToken' => 'device-token'],
            'https://api.example.com',
        ))->createConnector();
        $this->assertInstanceOf(AccessTokenConnector::class, $withDeviceToken);
    }

    /**
     * Data provider for testCreateConnectorFactoryBehavior() test.
     *
     * @return array<int, array{0: string|null, 1: class-string}>
     */
    public static function connectorFactoryProvider(): array
    {
        return [
            // Env set: should return PathRewriteConnector.
            ['1234-5678-uuid', PathRewriteConnector::class],
            // Env not set: should return Connector.
            [null, Connector::class],
        ];
    }

    /**
     * Restores the original environment variable after each test.
     */
    protected function tearDown(): void
    {
        if ($this->originalEnv === false) {
            putenv('AH_CODEBASE_UUID');
        } else {
            putenv('AH_CODEBASE_UUID=' . $this->originalEnv);
        }
        parent::tearDown();
    }
}
