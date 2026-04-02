<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\CloudApi\PathRewriteConnector;
use AcquiaCloudApi\Connector\Connector;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Acquia\Cli\CloudApi\ConnectorFactory
 *
 * Unit tests for the ConnectorFactory. Ensures that the factory returns the correct
 * connector type depending on the presence of the AH_CODEBASE_UUID environment variable.
 */
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


    /**
     * @dataProvider connectorFactoryProvider
     */
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
