<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use GuzzleHttp\Client;

class PullFilesCommandTest extends PullCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

        return new PullFilesCommand(
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
            $this->httpClientProphecy->reveal()
        );
    }

    /**
     * @throws \Exception
     */
    public function testPullFilesAcsf(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
        $selectedEnvironment = $environmentsResponse->_embedded->items[0];
        $sshHelper = $this->mockSshHelper();
        $this->mockGetAcsfSites($sshHelper);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockGetFilesystem($localMachineHelper);
        $this->mockExecuteRsync($localMachineHelper, $selectedEnvironment, '/mnt/files/profserv2.01dev/sites/g/files/jxr5000596dev/files/', $this->projectDir . '/docroot/sites/jxr5000596dev/files');

        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose an Acquia environment:
            0,
            // Choose site from which to copy files:
            0,
        ];

        $this->executeCommand([], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    }

    /**
     * @throws \Exception
     */
    public function testPullFilesAcsfNoSites(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $this->mockAcsfEnvironmentsRequest($applicationsResponse);
        $sshHelper = $this->mockSshHelper();
        $this->mockGetAcsfSites($sshHelper, false);
        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose an Acquia environment:
            0,
            // Choose site from which to copy files:
            0,
        ];


        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No sites found in this environment');
        $this->executeCommand([], $inputs);
    }

    /**
     * @throws \Exception
     */
    public function testPullFilesCloud(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
        $selectedEnvironment = $environmentsResponse->_embedded->items[0];
        $sshHelper = $this->mockSshHelper();
        $this->mockGetCloudSites($sshHelper, $selectedEnvironment);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockGetFilesystem($localMachineHelper);
        $parts = explode('.', $selectedEnvironment->ssh_url);
        $sitegroup = reset($parts);
        $this->mockExecuteRsync($localMachineHelper, $selectedEnvironment, '/mnt/files/' . $sitegroup . '.' . $selectedEnvironment->name . '/sites/default/files/', $this->projectDir . '/docroot/sites/default/files');

        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose an Acquia environment:
            0,
            // Choose site from which to copy files:
            0,
        ];

        $this->executeCommand([], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    }

    /**
     * @throws \Exception
     */
    public function testInvalidCwd(): void
    {
        IdeHelper::setCloudIdeEnvVars();
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockDrupalSettingsRefresh($localMachineHelper);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Run this command from the ');
        $this->executeCommand();
        IdeHelper::unsetCloudIdeEnvVars();
    }

    /**
     * @throws \Exception
     */
    public function testPullFilesCodebaseUuid(): void
    {
        $codebaseUuid = '11111111-041c-44c7-a486-7972ed2cafc8';
        self::setEnvVars([
            'AH_CODEBASE_UUID' => $codebaseUuid,
        ]);
        $expectedCodebase = $this->getMockCodebaseResponse();
        $expectedCodebases = $this->getMockCodebasesResponse();

        $expectedCodebaseEnvironments = $this->getMockCodeBaseEnvironments();
        $expectedCodebaseSitesData = $this->getMockCodeBaseSites();
        $expectedCodebaseSites = $expectedCodebaseSitesData->_embedded->items;
        $expectedSiteInstance = $this->getMockSiteInstanceResponse();
        $this->clientProphecy->request('get', '/sites/' . $expectedCodebaseSites[0]->id)
            ->willReturn($expectedCodebaseSites[0])
            ->shouldBeCalled();
        $this->clientProphecy->request('get', '/site-instances/' . $expectedCodebaseSites[0]->id . "." . $expectedCodebaseEnvironments->_embedded->items[0]->id)
            ->willReturn($expectedSiteInstance)
            ->shouldBeCalled();

        // Only set prophecy expectations for the actual calls made.
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid)
            ->willReturn($expectedCodebase)
            ->shouldBeCalled();

        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/environments')
            ->willReturn($expectedCodebaseEnvironments->_embedded->items)
            ->shouldBeCalled();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/sites')
            ->willReturn($expectedCodebaseSites)
            ->shouldBeCalled();
        $selectedEnvironment =  $expectedCodebaseEnvironments->_embedded->items[0];
        $sshHelper = $this->mockSshHelper();

        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockGetFilesystem($localMachineHelper);
        $parts = explode('.', $selectedEnvironment->ssh_url);
        $sitegroup = reset($parts);
        $this->mockExecuteRsync($localMachineHelper, $selectedEnvironment, '/mnt/files/' . $sitegroup . '.' . $selectedEnvironment->name . '/sites/site2/files/', $this->projectDir . '/docroot/sites/site2/files');

        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Choose a site [default]:
            0,
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            // 'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose an Acquia environment:
            0,
            // Choose site from which to copy files:
            0,
        ];

        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Detected IDE context with codebase UUID: ', $output);
        // $this->assertStringContainsString('Using codebase:', $output);
        // $this->assertStringContainsString('Using site instance:', $output);
        // $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        self::unsetEnvVars(["AH_CODEBASE_UUID"]);
    }
}
