<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Push\PushFilesCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class PushFilesCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(PushFilesCommand::class);
    }

    public function testPushFilesAcsf(): void
    {
        $siteInstanceResponse = $this->mockSiteInstanceRequest();
        $sshHelper = $this->mockSshHelper();
        $multisiteConfig = $this->mockGetAcsfSites($sshHelper);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $process = $this->mockProcess();
        $this->mockExecuteAcsfRsync($localMachineHelper, $process, reset($multisiteConfig['sites'])['name']);

        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose a Cloud Platform environment.
            0,
            // Choose a site.
            0,
            // Overwrite the public files directory.
            'y',
        ];

        $this->executeCommand(['siteInstanceId' => $siteInstanceResponse->site_id . "." . $siteInstanceResponse->environment_id], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    }

    public function testPushFilesCloud(): void
    {
        $siteInstanceResponse = $this->mockSiteInstanceRequest();
        $sshHelper = $this->mockSshHelper();
        $this->mockGetCloudSites($sshHelper, $siteInstanceResponse);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $process = $this->mockProcess();
        $this->mockExecuteCloudRsync($localMachineHelper, $process, $siteInstanceResponse);

        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose a Cloud Platform environment.
            0,
            // Choose a site.
            0,
            // Overwrite the public files directory.
            'y',
        ];

        $this->executeCommand(['siteInstanceId' => $siteInstanceResponse->site_id . "." . $siteInstanceResponse->environment_id], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    }

    public function testPushFilesNoOverwrite(): void
    {

        $siteInstanceResponse = $this->mockSiteInstanceRequest();
        $sshHelper = $this->mockSshHelper();
        $this->mockGetCloudSites($sshHelper, $siteInstanceResponse);
        $localMachineHelper = $this->mockLocalMachineHelper();

        $this->command->sshHelper = $sshHelper->reveal();

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose a Cloud Platform environment.
            0,
            // Choose a site.
            0,
            // Overwrite the public files directory.
            'n',
        ];

        $this->executeCommand(['siteInstanceId' => $siteInstanceResponse->site_id . "." . $siteInstanceResponse->environment_id], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringNotContainsString('Pushing public files', $output);
    }

    protected function mockExecuteCloudRsync(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process,
        mixed $siteInstance
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(['rsync'])
            ->shouldBeCalled();
        $sitegroup = $siteInstance->site_id;
        $command = [
            'rsync',
            '-avPhze',
            'ssh -o StrictHostKeyChecking=no',
            $this->projectDir . '/docroot/sites/default/files/',
            $siteInstance->environment->codebase->vcs_url . ':/mnt/files/' . $sitegroup . '.' . $siteInstance->environment->name . '/sites/default/files',
        ];
        $localMachineHelper->execute($command, Argument::type('callable'), null, true)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteAcsfRsync(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process,
        string $site
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(['rsync'])
            ->shouldBeCalled();
        $command = [
            'rsync',
            '-avPhze',
            'ssh -o StrictHostKeyChecking=no',
            $this->projectDir . '/docroot/sites/' . $site . '/files/',
            'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/files/profserv2.01dev/sites/g/files/' . $site . '/files',
        ];
        $localMachineHelper->execute($command, Argument::type('callable'), null, true)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }
}
