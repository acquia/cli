<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\SasApi;

use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\SasApi\SasClient;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\SasApi\SasConnectorFactory;
use Acquia\Cli\Tests\TestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the SasClientService.
 */
#[CoversClass(SasClientService::class)]
class SasClientServiceTest extends TestBase
{
    public function testGetClientReturnsConfiguredSasClient(): void
    {
        $factory = new SasConnectorFactory(
            ['key' => 'k', 'secret' => 's', 'accessToken' => null, 'accessTokenExpiry' => null],
            'https://sas.example.com',
            'https://accounts.example.com',
        );
        $service = new SasClientService($factory, $this->application, new CloudCredentials($this->datastoreCloud));

        $client = $service->getClient();

        // The parent constructor must have run for the connector to be set and
        // a client to be produced.
        $this->assertInstanceOf(SasClient::class, $client);
    }
}
