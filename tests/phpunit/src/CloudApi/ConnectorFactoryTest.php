<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\CloudApi\PathRewriteConnector;
use AcquiaCloudApi\Connector\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorFactoryTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testCreateConnectorReturnsPathRewriteConnectorIfEnvSet(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $factory = new ConnectorFactory(['key' => 'k', 'secret' => 's'], 'https://api.example.com');
        $connector = $factory->createConnector();
        $this->assertInstanceOf(PathRewriteConnector::class, $connector);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateConnectorReturnsRegularConnectorIfEnvNotSet(): void
    {
        putenv('AH_CODEBASE_UUID');
        $factory = new ConnectorFactory(['key' => 'k', 'secret' => 's'], 'https://api.example.com');
        $connector = $factory->createConnector();
        $this->assertInstanceOf(Connector::class, $connector);
    }
}
