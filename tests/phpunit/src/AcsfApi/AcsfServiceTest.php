<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Application;
use Acquia\Cli\Tests\TestBase;
use PHPUnit\Framework\Attributes\DataProvider;

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
    public static function providerTestIsMachineAuthenticated(): array
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

    #[DataProvider('providerTestIsMachineAuthenticated')]
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

    public function testACloudPlatformDeviceTokenDoesNotAuthenticateAcsf(): void
    {
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'device_token' => [
                'access_token' => 'cloud-platform-token',
                'client_id' => 'client-123',
                'expiry' => time() + 300,
                'refresh_token' => 'cloud-platform-refresh-token',
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $clientService = new AcsfClientService(new AcsfConnectorFactory([
            'key' => null,
            'secret' => null,
        ]), $this->prophet->prophesize(Application::class)
            ->reveal(), new AcsfCredentials($this->datastoreCloud));

        $this->assertFalse($clientService->isMachineAuthenticated());
    }
}
