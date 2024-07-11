<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Application;
use Acquia\Cli\Tests\TestBase;

class AcsfServiceTest extends TestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    }

    /**
     * @return array<mixed>
     */
    public function providerTestIsMachineAuthenticated(): array
    {
        return [
            [
                ['ACSF_USERNAME' => 'key', 'ACSF_KEY' => 'secret'],
                true,
            ],
            [
                ['ACSF_USERNAME' => 'key', 'ACSF_KEY' => 'secret'],
                true,
            ],
            [
                ['ACSF_USERNAME' => null, 'ACSF_KEY' => null],
                false,
            ],
            [
                ['ACSF_USERNAME' => 'key', 'ACSF_KEY' => null],
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
        $clientService = new AcsfClientService(new AcsfConnectorFactory([
            'key' => null,
            'secret' => null,
        ]), $this->prophet->prophesize(Application::class)
            ->reveal(), $this->cloudCredentials);
        $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
        self::unsetEnvVars($envVars);
    }
}
