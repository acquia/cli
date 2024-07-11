<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property \Acquia\Cli\Command\Pull\PullDatabaseCommand $command
 */
class PullDatabaseCommandTest extends PullCommandTestBase
{
    /**
     * @return int[][]
     */
    public function providerTestPullDatabaseWithInvalidSslCertificate(): array
    {
        return [[51], [60]];
    }

    protected function createCommand(): CommandBase
    {
        $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

        return new PullDatabaseCommand(
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
            $this->httpClientProphecy->reveal()
        );
    }

    public function testPullDatabases(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $environment = $this->mockGetEnvironment();
        $sshHelper = $this->mockSshHelper();
        $this->mockListSites($sshHelper);
        $this->mockGetBackup($environment);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, true, true, 'my_db', 'my_dbdev', 'drupal');
        $fs->remove(Argument::type('string'))->shouldBeCalled();
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        $this->mockExecuteDrushExists($localMachineHelper);
        $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        $process = $this->mockProcess();
        $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);
        $this->mockExecuteDrushSqlSanitize($localMachineHelper, $process);

        $this->executeCommand([
            '--no-scripts' => false,
        ], self::inputChooseEnvironment());

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringContainsString('Choose a database [my_db (default)]:', $output);
        $this->assertStringContainsString('Downloading backup 1', $output);
    }

    public function testPullProdDatabase(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $environments = $this->mockRequest('getApplicationEnvironments', $application->uuid);
        $environment = $environments[1];
        $sshHelper = $this->mockSshHelper();
        $process = $this->mockProcess();
        $process->getOutput()->willReturn('default')->shouldBeCalled();
        $sshHelper->executeCommand(Argument::type('string'), ['ls', '/mnt/files/site.prod/sites'], false)
        ->willReturn($process->reveal())->shouldBeCalled();
        $this->mockGetBackup($environment);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, true, true, 'my_db', 'my_dbprod', 'drupal', 'prod');
        $fs->remove(Argument::type('string'))->shouldBeCalled();
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();

        $this->executeCommand([
            '--no-scripts' => true,
        ], [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
        // Select a Cloud Platform application:
            self::$INPUT_DEFAULT_CHOICE,
        // Would you like to link the project at ... ?
            'n',
        // Choose an Acquia environment:
            1,
        ]);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringContainsString('Choose a database [my_db (default)]:', $output);
        $this->assertStringContainsString('Downloading backup 1', $output);
    }

    public function testPullDatabasesLocalConnectionFailure(): void
    {
        $this->mockGetEnvironment();
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, false);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Unable to connect');
        $this->executeCommand([
            '--no-scripts' => true,
        ], self::inputChooseEnvironment());
    }

    public function testPullDatabaseNoPv(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $environment = $this->mockGetEnvironment();
        $sshHelper = $this->mockSshHelper();
        $this->mockListSites($sshHelper);
        $this->mockGetBackup($environment);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, true, false, 'my_db', 'my_dbdev', 'drupal');
        $fs->remove(Argument::type('string'))->shouldBeCalled();

        $this->executeCommand([
            '--no-scripts' => true,
        ], self::inputChooseEnvironment());

        $output = $this->getDisplay();

        $this->assertStringContainsString(' [WARNING] Install `pv` to see progress bar', $output);
    }

    public function testPullMultipleDatabases(): void
    {
        $this->setupPullDatabase(true, true, false, true, true);
        $inputs = [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
        // Select a Cloud Platform application:
            0,
        // Would you like to link the project at ... ?
            'n',
        // Choose a Cloud Platform environment [Dev, dev (vcs: master)]:
            0,
        // Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:
            0,
        // Choose databases. You may choose multiple. Use commas to separate choices. [profserv2 (default)]:
            '10,27',
        ];
        $this->executeCommand([
            '--multiple-dbs' => true,
            '--no-scripts' => true,
        ], $inputs);
    }

    public function testPullDatabasesOnDemand(): void
    {
        $this->setupPullDatabase(true, true, true);
        $inputs = self::inputChooseEnvironment();

        $this->executeCommand([
            '--no-scripts' => true,
            '--on-demand' => true,
        ], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringContainsString('Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:', $output);
        $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    }

    public function testPullDatabasesNoExistingBackup(): void
    {
        $this->setupPullDatabase(true, true, true, true, false, 0, false);
        $inputs = self::inputChooseEnvironment();

        $this->executeCommand([
            '--no-scripts' => true,
        ], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringContainsString('Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:', $output);
        $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
        $this->assertStringContainsString('No existing backups found, creating an on-demand backup now.', $output);
    }

    public function testPullDatabasesSiteArgument(): void
    {
        $this->setupPullDatabase(true, true, false, false);
        $inputs = self::inputChooseEnvironment();

        $this->executeCommand([
            '--no-scripts' => true,
            'site' => 'jxr5000596dev',
        ], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringNotContainsString('Choose a database', $output);
    }

    public function testPullDatabaseWithMySqlDropError(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $environment = $this->mockGetEnvironment();
        $sshHelper = $this->mockSshHelper();
        $this->mockListSites($sshHelper);
        $this->mockGetBackup($environment);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        $this->mockExecuteMySqlDropDb($localMachineHelper, false, $fs);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Unable to drop tables from database');
        $this->executeCommand([
            '--no-scripts' => true,
        ], self::inputChooseEnvironment());
    }

    public function testPullDatabaseWithMySqlImportError(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $environment = $this->mockGetEnvironment();
        $sshHelper = $this->mockSshHelper();
        $this->mockListSites($sshHelper);
        $this->mockGetBackup($environment);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, false, true, 'my_db', 'my_dbdev', 'drupal');

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Unable to import local database');
        $this->executeCommand([
            '--no-scripts' => true,
        ], self::inputChooseEnvironment());
    }

    /**
     * @dataProvider providerTestPullDatabaseWithInvalidSslCertificate
     */
    public function testPullDatabaseWithInvalidSslCertificate(int $errorCode): void
    {
        $this->setupPullDatabase(true, false, false, true, false, $errorCode);
        $inputs = self::inputChooseEnvironment();

        $this->executeCommand(['--no-scripts' => true], $inputs);
        $output = $this->getDisplay();
        $this->assertStringContainsString('The certificate for www.example.com is invalid.', $output);
        $this->assertStringContainsString('Trying alternative host other.example.com', $output);
    }

    protected function setupPullDatabase(bool $mysqlConnectSuccessful, bool $mockIdeFs = false, bool $onDemand = false, bool $mockGetAcsfSites = true, bool $multiDb = false, int $curlCode = 0, bool $existingBackups = true): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
        $selectedEnvironment = $environmentsResponse->_embedded->items[0];
        $this->createMockGitConfigFile();

        $databasesResponse = $this->mockAcsfDatabasesResponse($selectedEnvironment);
        $databaseResponse = $databasesResponse[array_search('jxr5000596dev', array_column($databasesResponse, 'name'), true)];
        $databaseBackupsResponse = $this->mockDatabaseBackupsResponse($selectedEnvironment, $databaseResponse->name, 1, $existingBackups);
        $selectedDatabase = $this->mockDownloadBackup($databaseResponse, $selectedEnvironment, $databaseBackupsResponse->_embedded->items[0], $curlCode);

        if ($multiDb) {
            $databaseResponse2 = $databasesResponse[array_search('profserv2', array_column($databasesResponse, 'name'), true)];
            $databaseBackupsResponse2 = $this->mockDatabaseBackupsResponse($selectedEnvironment, $databaseResponse2->name, 1, $existingBackups);
            $this->mockDownloadBackup($databaseResponse2, $selectedEnvironment, $databaseBackupsResponse2->_embedded->items[0], $curlCode);
        }

        $sshHelper = $this->mockSshHelper();
        if ($mockGetAcsfSites) {
            $this->mockGetAcsfSites($sshHelper);
        }

        if ($onDemand) {
            $backupResponse = $this->mockDatabaseBackupCreateResponse($selectedEnvironment, $selectedDatabase->name);
            // Cloud API does not provide the notification UUID as part of the backup response, so we must hardcode it.
            $this->mockNotificationResponseFromObject($backupResponse);
        }

        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, $mysqlConnectSuccessful);
        // Set up file system.
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();

        // Mock IDE filesystem.
        if ($mockIdeFs) {
            $this->mockDrupalSettingsRefresh($localMachineHelper);
        }
        $this->mockSettingsFiles($fs);

        // Database.
        $this->mockExecuteMySqlListTables($localMachineHelper);
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, true, true);
        if ($multiDb) {
            $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
            $this->mockExecuteMySqlImport($localMachineHelper, true, true, 'profserv2', 'profserv2dev', 'drupal');
        }
    }

    protected function mockSshHelper(): ObjectProphecy|SshHelper
    {
        $sshHelper = parent::mockSshHelper();
        $this->command->sshHelper = $sshHelper->reveal();
        return $sshHelper;
    }

    public function testDownloadProgressDisplay(): void
    {
        $output = new BufferedOutput();
        $progress = null;
        PullCommandBase::displayDownloadProgress(100, 0, $progress, $output);
        $this->assertStringContainsString('0/100 [💧---------------------------]   0%', $output->fetch());

        // Need to sleep to prevent the default redraw frequency from skipping display.
        sleep(1);
        PullCommandBase::displayDownloadProgress(100, 50, $progress, $output);
        $this->assertStringContainsString('50/100 [==============💧-------------]  50%', $output->fetch());

        PullCommandBase::displayDownloadProgress(100, 100, $progress, $output);
        $this->assertStringContainsString('100/100 [============================] 100%', $output->fetch());
    }

    public function testPullNode(): void
    {
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $tamper = function ($responses): void {
            foreach ($responses as $response) {
                $response->type = 'node';
            }
        };
        $this->mockRequest('getApplicationEnvironments', $application->uuid, null, null, $tamper);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No compatible environments found');
        $this->executeCommand([
            '--no-scripts' => true,
        ], [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
        // Select a Cloud Platform application:
            self::$INPUT_DEFAULT_CHOICE,
        // Would you like to link the project at ... ?
            'n',
        // Choose an Acquia environment:
            1,
        ]);
    }
}
