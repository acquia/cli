<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Push\PushDatabaseCommand;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\Push\PushDatabaseCommand $command
 */
class PushDatabaseCommandTest extends CommandTestBase
{
    /**
     * @return mixed[]
     */
    public function providerTestPushDatabase(): array
    {
        return [
            [OutputInterface::VERBOSITY_NORMAL, false],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, true],
        ];
    }

    protected function createCommand(): CommandBase
    {

        return $this->injectCommand(PushDatabaseCommand::class);
    }

    public function setUp(): void
    {
        self::unsetEnvVars([
            'ACLI_DB_HOST',
            'ACLI_DB_USER',
            'ACLI_DB_PASSWORD',
            'ACLI_DB_NAME',
        ]);
        parent::setUp();
    }

    /**
     * @dataProvider providerTestPushDatabase
     */
    public function testPushDatabase(int $verbosity, bool $printOutput): void
    {
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $tamper = function ($responses): void {
            foreach ($responses as $response) {
                $response->ssh_url = 'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com';
                $response->domains = ["profserv201dev.enterprise-g1.acquia-sites.com"];
            }
        };
        $environments = $this->mockRequest('getApplicationEnvironments', $application->uuid, null, null, $tamper);
        $this->createMockGitConfigFile();
        $this->mockAcsfDatabasesResponse($environments[self::$INPUT_DEFAULT_CHOICE]);
        $process = $this->mockProcess();

        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])
            ->shouldBeCalled();
        $this->mockGetAcsfSitesLMH($localMachineHelper);

        // Database.
        $this->mockExecutePvExists($localMachineHelper);
        $this->mockCreateMySqlDumpOnLocal($localMachineHelper, $printOutput);
        $this->mockUploadDatabaseDump($localMachineHelper, $process, $printOutput);
        $this->mockImportDatabaseDumpOnRemote($localMachineHelper, $process, $printOutput);

        $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);

        $inputs = [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            0,
            // Would you like to link the project at ... ?
            'n',
            // Choose a Cloud Platform environment.
            0,
            // Choose a database.
            0,
            // Overwrite the profserv2 database on dev with a copy of the database from the current machine?
            'y',
        ];

        $this->executeCommand([], $inputs, $verbosity);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringContainsString('Choose a database', $output);
        $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
        $this->assertStringContainsString('profserv2 (default)', $output);
        $this->assertStringContainsString('Overwrite the jxr136 database on dev with a copy of the database from the current machine?', $output);
    }

    protected function mockUploadDatabaseDump(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process,
        bool $printOutput = true,
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(['rsync'])
            ->shouldBeCalled();
        $command = [
            'rsync',
            '-tDvPhe',
            'ssh -o StrictHostKeyChecking=no',
            sys_get_temp_dir() . '/acli-mysql-dump-drupal.sql.gz',
            'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/tmp/profserv2.01dev/acli-mysql-dump-drupal.sql.gz',
        ];
        $localMachineHelper->execute($command, Argument::type('callable'), null, $printOutput, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteMySqlImport(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process
    ): void {
        // MySQL import command.
        $localMachineHelper
            ->executeFromCmd(
                Argument::type('string'),
                Argument::type('callable'),
                null,
                true,
                null
            )
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockGetAcsfSitesLMH(ObjectProphecy $localMachineHelper): void
    {
        $acsfMultisiteFetchProcess = $this->mockProcess();
        $multisiteConfig = file_get_contents(Path::join($this->realFixtureDir, '/multisite-config.json'));
        $acsfMultisiteFetchProcess->getOutput()
            ->willReturn($multisiteConfig)
            ->shouldBeCalled();
        $cmd = [
            0 => 'ssh',
            1 => 'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com',
            2 => '-t',
            3 => '-o StrictHostKeyChecking=no',
            4 => '-o AddressFamily inet',
            5 => '-o LogLevel=ERROR',
            6 => 'cat',
            7 => '/var/www/site-php/profserv2.01dev/multisite-config.json',
        ];
        $localMachineHelper->execute($cmd, Argument::type('callable'), null, false, null)
            ->willReturn($acsfMultisiteFetchProcess->reveal())
            ->shouldBeCalled();
    }

    private function mockImportDatabaseDumpOnRemote(ObjectProphecy|LocalMachineHelper $localMachineHelper, Process|ObjectProphecy $process, bool $printOutput = true): void
    {
        $cmd = [
            0 => 'ssh',
            1 => 'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com',
            2 => '-t',
            3 => '-o StrictHostKeyChecking=no',
            4 => '-o AddressFamily inet',
            5 => '-o LogLevel=ERROR',
            6 => 'pv /mnt/tmp/profserv2.01dev/acli-mysql-dump-drupal.sql.gz --bytes --rate | gunzip | MYSQL_PWD=password mysql --host=fsdb-74.enterprise-g1.hosting.acquia.com.enterprise-g1.hosting.acquia.com --user=s164 profserv2db14390',
        ];
        $localMachineHelper->execute($cmd, Argument::type('callable'), null, $printOutput, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }
}
