<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\SasApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\SasApi\SasConnector;
use Acquia\Cli\SasApi\SasConnectorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SasConnectorFactory. Ensures the factory returns the
 * correct connector type depending on the available credentials.
 */
#[CoversClass(SasConnectorFactory::class)]
class SasConnectorFactoryTest extends TestCase
{
    /**
     * @return array<int, array{0: array<string, string|null>, 1: class-string}>
     */
    public static function connectorProvider(): array
    {
        return [
            // An expired access token falls back to an unauthenticated connector.
            'expired token' => [
                ['key' => null, 'secret' => null, 'accessToken' => 'tok', 'accessTokenExpiry' => (string) (time() - 3600)],
                SasConnector::class,
            ],
            // Key & secret take priority and produce the standard connector.
            'key+secret' => [
                ['key' => 'k', 'secret' => 's', 'accessToken' => null, 'accessTokenExpiry' => null],
                SasConnector::class,
            ],
            // Key without secret is not enough for key/secret auth.
            'key only' => [
                ['key' => 'k', 'secret' => null, 'accessToken' => null, 'accessTokenExpiry' => null],
                SasConnector::class,
            ],
            // No credentials at all: unauthenticated connector.
            'no credentials' => [
                ['key' => null, 'secret' => null, 'accessToken' => null, 'accessTokenExpiry' => null],
                SasConnector::class,
            ],
            // Secret without key is not enough either.
            'secret only' => [
                ['key' => null, 'secret' => 's', 'accessToken' => null, 'accessTokenExpiry' => null],
                SasConnector::class,
            ],
            // A valid (unexpired) access token produces an AccessTokenConnector.
            'valid token' => [
                ['key' => null, 'secret' => null, 'accessToken' => 'tok', 'accessTokenExpiry' => (string) (time() + 3600)],
                AccessTokenConnector::class,
            ],
        ];
    }

    /**
     * @param array<string, string|null> $config
     */
    #[DataProvider('connectorProvider')]
    public function testCreateConnectorSelectsCorrectType(array $config, string $expectedClass): void
    {
        $factory = new SasConnectorFactory($config, 'https://sas.example.com', 'https://accounts.example.com');
        // Assert the exact concrete class so a flipped condition (&&/||, or a
        // negated operand) that routes to the wrong branch fails the test.
        $this->assertSame($expectedClass, get_class($factory->createConnector()));
    }
}
