<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\SasApi;

use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\SasApi\SasCredentials;
use Acquia\Cli\Tests\TestBase;

class SasClientServiceTest extends TestBase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ACLI_SAS_BASE_URI');
    }

    public function testConstructs(): void
    {
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $credentials = new SasCredentials($cloudDatastore->reveal());
        $connectorFactory = new ConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]);
        $sasService = new SasClientService($connectorFactory, $this->application, $credentials);
        $this->assertFalse($sasService->isMachineAuthenticated());
    }

    public function testConnectorUsesSasBaseUri(): void
    {
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $credentials = new SasCredentials($cloudDatastore->reveal());
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => null,
                'key' => null,
                'secret' => null,
            ],
            $credentials->getBaseUri()
        );
        $sasService = new SasClientService($connectorFactory, $this->application, $credentials);
        $this->assertEquals(
            'https://sites-aggregation-service-prod.prod.cicd.acquia.io/api',
            $connectorFactory->createConnector()->getBaseUri()
        );
        $this->assertFalse($sasService->isMachineAuthenticated());
    }

    public function testConnectorUsesEnvVarBaseUri(): void
    {
        $sasUri = 'https://sites-aggregation-service.qa.cicd.acquia.io/api';
        putenv('ACLI_SAS_BASE_URI=' . $sasUri);
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $credentials = new SasCredentials($cloudDatastore->reveal());
        $connectorFactory = new ConnectorFactory(
            [
                'accessToken' => null,
                'key' => null,
                'secret' => null,
            ],
            $credentials->getBaseUri()
        );
        $this->assertEquals($sasUri, $connectorFactory->createConnector()->getBaseUri());
    }
}
