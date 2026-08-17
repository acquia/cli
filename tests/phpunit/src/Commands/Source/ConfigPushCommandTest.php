<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPushCommand;
use Acquia\Cli\SasApi\SasClient;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @property ConfigPushCommand $command
 */
class ConfigPushCommandTest extends CommandTestBase
{
    /**
     * The site instance ID the tests resolve against.
     */
    private const SITE_ID = '0ebce493-9d09-479d-a9a8-138a206fa687';
    private const ENVIRONMENT_ID = '3e8ecbec-ea7c-4260-8414-ef2938c859bc';
    private const SITE_INSTANCE_ID = self::SITE_ID . '.' . self::ENVIRONMENT_ID;

    private SasClientService|ObjectProphecy $sasClientServiceProphecy;

    private SasClient|ObjectProphecy $sasClientProphecy;

    protected function createCommand(): CommandBase
    {
        $this->sasClientProphecy = $this->prophet->prophesize(SasClient::class);
        $this->sasClientServiceProphecy = $this->prophet->prophesize(SasClientService::class);
        $this->sasClientServiceProphecy->getClient()->willReturn($this->sasClientProphecy->reveal());

        return new ConfigPushCommand(
            $this->localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->acliRepoRoot,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
            $this->sasClientServiceProphecy->reveal(),
        );
    }

    /**
     * Mock the Cloud API calls needed to resolve a site instance from
     * --siteInstanceId: environment, site, site instance, and codebase.
     */
    private function mockSiteInstanceResolution(): void
    {
        $environment = $this->getMockCodeBaseEnvironment();
        $this->clientProphecy->request('get', '/v3/environments/' . self::ENVIRONMENT_ID)
            ->willReturn($environment)
            ->shouldBeCalled();

        $site = $this->getMockSite();
        $this->clientProphecy->request('get', '/sites/' . self::SITE_ID)
            ->willReturn($site)
            ->shouldBeCalled();

        $siteInstance = $this->getMockSiteInstanceResponse();
        $this->clientProphecy->request('get', '/site-instances/' . self::SITE_INSTANCE_ID)
            ->willReturn($siteInstance)
            ->shouldBeCalled();

        $codebase = $this->getMockCodebaseResponse();
        $this->clientProphecy->request('get', '/codebases/d3f7270e-c45f-4801-9308-5e8afe84a323')
            ->willReturn($codebase)
            ->shouldBeCalled();
    }

    public function testExecutePushesAndPollsToCompletion(): void
    {
        $this->mockSiteInstanceResolution();

        // The push trigger returns an operation ID.
        $this->sasClientProphecy->request('post', '/environments/' . self::ENVIRONMENT_ID . '/config-import')
            ->willReturn((object) ['id' => 'operation-123'])
            ->shouldBeCalled();

        // The status poll immediately reports success.
        $this->sasClientProphecy->request('get', '/config-operation/operation-123')
            ->willReturn((object) ['status' => 'succeeded'])
            ->shouldBeCalled();

        $this->executeCommand(
            ['--siteInstanceId' => self::SITE_INSTANCE_ID, '--force' => true],
        );

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Importing configuration', $this->getDisplay());
    }

    public function testExecuteThrowsWhenOperationIdMissing(): void
    {
        $this->mockSiteInstanceResolution();

        // The push trigger returns a response with no operation ID.
        $this->sasClientProphecy->request('post', '/environments/' . self::ENVIRONMENT_ID . '/config-import')
            ->willReturn((object) [])
            ->shouldBeCalled();

        $this->expectException(\Acquia\Cli\Exception\AcquiaCliException::class);
        $this->expectExceptionMessage('did not include an operation ID');

        $this->executeCommand(
            ['--siteInstanceId' => self::SITE_INSTANCE_ID, '--force' => true],
        );
    }

    public function testExecuteFailsWhenOperationFails(): void
    {
        $this->mockSiteInstanceResolution();

        $this->sasClientProphecy->request('post', '/environments/' . self::ENVIRONMENT_ID . '/config-import')
            ->willReturn((object) ['id' => 'operation-456'])
            ->shouldBeCalled();

        // The status poll reports failure.
        $this->sasClientProphecy->request('get', '/config-operation/operation-456')
            ->willReturn((object) ['status' => 'failed'])
            ->shouldBeCalled();

        $this->executeCommand(
            ['--siteInstanceId' => self::SITE_INSTANCE_ID, '--force' => true],
        );

        $this->assertSame(1, $this->getStatusCode());
        $this->assertStringContainsString('failed', $this->getDisplay());
    }
}
