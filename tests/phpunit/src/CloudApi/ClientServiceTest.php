<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

class ClientServiceTest extends TestBase
{
    /**
     * @return array<mixed>
     */
    public function providerTestIsMachineAuthenticated(): array
    {
        return [
            [
                [
                    'ACLI_ACCESS_TOKEN' => 'token',
                    'ACLI_KEY' => 'key',
                    'ACLI_SECRET' => 'secret',
                ],
                true,
            ],
            [
                [
                    'ACLI_ACCESS_TOKEN' => null,
                    'ACLI_KEY' => 'key',
                    'ACLI_SECRET' => 'secret',
                ],
                true,
            ],
            [
                [
                    'ACLI_ACCESS_TOKEN' => null,
                    'ACLI_KEY' => null,
                    'ACLI_SECRET' => null,
                ],
                false,
            ],
            [
                [
                    'ACLI_ACCESS_TOKEN' => null,
                    'ACLI_KEY' => 'key',
                    'ACLI_SECRET' => null,
                ],
                false,
            ],
        ];
    }

    /**
     * @dataProvider providerTestIsMachineAuthenticated
     */
    public function testIsMachineAuthenticated(array $envVars, bool $isAuthenticated): void
    {
        self::setEnvVars($envVars);
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new ClientService(new ConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new CloudCredentials($cloudDatastore->reveal()));
        $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
        self::unsetEnvVars($envVars);
    }
}
