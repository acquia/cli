<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\SasApi;

use Acquia\Cli\SasApi\SasConnector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SasConnector.
 */
#[CoversClass(SasConnector::class)]
class SasConnectorTest extends TestCase
{
    public function testConstructorSetsBaseUri(): void
    {
        $connector = new SasConnector(
            ['key' => 'k', 'secret' => 's'],
            'https://sas.example.com',
        );

        // The base URI is passed through to the parent connector.
        $this->assertSame('https://sas.example.com', $connector->getBaseUri());
    }

    public function testConstructorDefaultsToCloudBaseUriWhenNotOverridden(): void
    {
        $connector = new SasConnector(['key' => 'k', 'secret' => 's']);

        // With no override, the parent default applies.
        $this->assertNotEmpty($connector->getBaseUri());
    }
}
