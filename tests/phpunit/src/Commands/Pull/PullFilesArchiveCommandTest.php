<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullFilesArchiveCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Filesystem;

class PullFilesArchiveCommandTest extends PullCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

        return new PullFilesArchiveCommand(
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
    public function testPullFilesArchiveCloud(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
        $selectedEnvironment = $environmentsResponse->_embedded->items[0];
        $sshHelper = $this->mockSshHelper();
        $this->mockGetCloudSites($sshHelper, $selectedEnvironment);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $parts = explode('.', $selectedEnvironment->ssh_url);
        $sitegroup = reset($parts);
        $this->mockExecuteFilesArchiveDownload(
            $localMachineHelper,
            $selectedEnvironment,
            '/mnt/files/' . $sitegroup . '.' . $selectedEnvironment->name . '/sites/default/files',
            $this->projectDir . '/docroot/sites/default/files'
        );

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
        // Production environments must be offered (determineEnvironment is
        // called with $allowProduction = true).
        $this->assertStringContainsString('Production, prod', $output);
    }

    /**
     * @throws \Exception
     */
    public function testPullFilesArchiveCloudDownloadFails(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
        $selectedEnvironment = $environmentsResponse->_embedded->items[0];
        $sshHelper = $this->mockSshHelper();
        $this->mockGetCloudSites($sshHelper, $selectedEnvironment);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['ssh', 'tar'])
            ->shouldBeCalled();
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();
        $fileSystem->mkdir(Argument::type('string'))
            ->shouldBeCalled();
        // The temp tarball must be cleaned up even when the download fails.
        $fileSystem->remove(Argument::type('string'))
            ->shouldBeCalled();
        $failedProcess = $this->mockProcess(false);
        $localMachineHelper->executeFromCmd(
            Argument::containingString('"${:REMOTE_COMMAND}"'),
            Argument::type('callable'),
            null,
            false,
            null,
            Argument::type('array')
        )
            ->willReturn($failedProcess->reveal())
            ->shouldBeCalled();

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
        $this->expectExceptionMessage('Unable to download files. error');
        $this->executeCommand([], $inputs);
    }

    protected function mockExecuteFilesArchiveDownload(
        LocalMachineHelper|ObjectProphecy $localMachineHelper,
        mixed $environment,
        string $sourceDir,
        string $destinationDir
    ): void {
        $process = $this->mockProcess();
        $localMachineHelper->checkRequiredBinariesExist(['ssh', 'tar'])
            ->shouldBeCalled();
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();
        $fileSystem->mkdir($destinationDir)
            ->shouldBeCalled();
        // Note: tempnam() truncates the prefix to three characters on Windows,
        // so the temp path cannot be matched more precisely than "a string".
        $fileSystem->remove(Argument::type('string'))
            ->shouldBeCalled();
        $localMachineHelper->executeFromCmd(
            Argument::containingString('ssh -o StrictHostKeyChecking=accept-new "${:SSH_URL}" "${:REMOTE_COMMAND}"'),
            Argument::type('callable'),
            null,
            false,
            null,
            Argument::that(static function (array $env) use ($environment, $sourceDir): bool {
                return $env['SSH_URL'] === $environment->ssh_url
                    && $env['REMOTE_COMMAND'] === "tar -C $sourceDir -czf - .";
            })
        )
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $localMachineHelper->execute(
            Argument::that(static function (array $command) use ($destinationDir): bool {
                return $command[0] === 'tar'
                    && $command[1] === '-xzf'
                    && $command[3] === '-C'
                    && $command[4] === $destinationDir;
            }),
            Argument::type('callable'),
            null,
            false
        )
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }
}
