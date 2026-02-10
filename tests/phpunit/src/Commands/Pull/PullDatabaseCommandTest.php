<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Transformer\EnvironmentTransformer;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\SiteInstanceDatabaseBackupResponse;
use AcquiaCloudApi\Response\SiteInstanceDatabaseResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
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
    public static function providerTestPullDatabaseWithInvalidSslCertificate(): array
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
            $this->selfUpdateManager,
            $this->httpClientProphecy->reveal()
        );
    }

    public function testPullDatabases(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        // Force normal verbosity so printOutput should be false (> VERBOSITY_NORMAL)
        $this->output->setVerbosity(BufferedOutput::VERBOSITY_NORMAL);
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
        $this->assertStringContainsString('Using a database backup that is 1', $output);
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
        $sshHelper->executeCommand(Argument::type('string'), [
            'ls',
            '/mnt/files/site.prod/sites',
        ], false)
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
        ], \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringContainsString('Choose a database [my_db (default)]:', $output);
        $this->assertStringContainsString('Using a database backup that is 1', $output);
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
        ], self::inputChooseEnvironment(), \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL);
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

    public function testPullDatabasesOnDemandFail(): void
    {
        $this->setupPullDatabase(true, true, true, true, false, 0, true, false);
        $inputs = self::inputChooseEnvironment();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Cloud API failed to create a backup');
        $this->executeCommand([
            '--no-scripts' => true,
            '--on-demand' => true,
        ], $inputs);
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

    /**
     * Test that the getBackupDownloadUrl and setBackupDownloadUrl methods work correctly.
     */
    public function testBackupDownloadUrlGetterSetter(): void
    {
        // Test initial state (null).
        $this->assertNull($this->command->getBackupDownloadUrl());

        // Test setting with string.
        $backupUrl = 'https://www.example.com/download-backup';
        $this->command->setBackupDownloadUrl($backupUrl);
        $this->assertNotNull($this->command->getBackupDownloadUrl());
        $this->assertEquals($backupUrl, (string) $this->command->getBackupDownloadUrl());

        // Test setting with UriInterface.
        $uri = new Uri('https://other.example.com/download-backup');
        $this->command->setBackupDownloadUrl($uri);
        $this->assertEquals((string) $uri, (string) $this->command->getBackupDownloadUrl());
    }

    /**
     * Test that downloading a backup when file is missing fails with validation error.
     */
    public function testPullDatabaseWithMissingFile(): void
    {
        $this->setupPullDatabase(true, false, false, true, false, 0, true, true, 'missing');
        $inputs = self::inputChooseEnvironment();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Database backup download failed: file was not created');
        $this->executeCommand(['--no-scripts' => true], $inputs);
    }

    /**
     * Test that downloading a backup with HTTP error status fails with validation error (codebase path).
     */
    public function testPullDatabasesWithCodebaseUuidHttpError(): void
    {
        $codebaseUuid = '11111111-041c-44c7-a486-7972ed2cafc8';
        self::SetEnvVars(['AH_CODEBASE_UUID' => $codebaseUuid]);

        // Mock the codebase returned from /codebases/{uuid}.
        $codebase = $this->getMockCodeBaseResponse();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid)
            ->willReturn($codebase);

        // Build one codebase environment (so prompt is skipped).
        $codebaseEnv = $this->getMockCodeBaseEnvironment();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/environments')
            ->willReturn([$codebaseEnv])
            ->shouldBeCalled();

        $codebaseSites = $this->getMockCodeBaseSites();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/sites')
            ->willReturn($codebaseSites);
        $siteInstance = $this->getMockSiteInstanceResponse();

        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc')
            ->willReturn($siteInstance)
            ->shouldBeCalled();
        $siteId = '8979a8ac-80dc-4df8-b2f0-6be36554a370';
        $site = $this->getMockSite();
        $this->clientProphecy->request('get', '/sites/' . $siteId)
            ->willReturn($site)
            ->shouldBeCalled();
        $siteInstanceDatabase = $this->getMockSiteInstanceDatabaseResponse();
        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database')
            ->willReturn($siteInstanceDatabase)
            ->shouldBeCalled();
        $createSiteInstanceDatabaseBackup = $this->getMockSiteInstanceDatabaseBackupsResponse('post', '201');
        $this->clientProphecy->request('post', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database/backups')
            ->willReturn($createSiteInstanceDatabaseBackup);
        $siteInstanceDatabaseBackups = $this->getMockSiteInstanceDatabaseBackupsResponse();
        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database/backups')
            ->willReturn($siteInstanceDatabaseBackups->_embedded->items)
            ->shouldBeCalled();

        $url = "https://environment-service-php.acquia.com/api/environments/d3f7270e-c45f-4801-9308-5e8afe84a323/";
        $this->mockDownloadCodebaseBackup(
            EnvironmentTransformer::transformSiteInstanceDatabase(new SiteInstanceDatabaseResponse($siteInstanceDatabase)),
            $url,
            EnvironmentTransformer::transformSiteInstanceDatabaseBackup(new SiteInstanceDatabaseBackupResponse($siteInstanceDatabaseBackups->_embedded->items[0])),
            0,
            'http_error'
        );

        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        $fs->remove(Argument::type('string'))->shouldBeCalled();
        $inputs = self::inputChooseEnvironment();

        try {
            $this->expectException(AcquiaCliException::class);
            $this->expectExceptionMessage('Database backup download failed with HTTP status 500');
            $this->executeCommand([
                '--no-scripts' => true,
                '--on-demand' => false,
            ], $inputs);
        } finally {
            self::unsetEnvVars(['AH_CODEBASE_UUID']);
        }
    }

    protected function setupPullDatabase(bool $mysqlConnectSuccessful, bool $mockIdeFs = false, bool $onDemand = false, bool $mockGetAcsfSites = true, bool $multiDb = false, int $curlCode = 0, bool $existingBackups = true, bool $onDemandSuccess = true, string $validationError = ''): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
        $selectedEnvironment = $environmentsResponse->_embedded->items[0];
        $this->createMockGitConfigFile();

        $databasesResponse = $this->mockAcsfDatabasesResponse($selectedEnvironment);
        $databaseResponse = $databasesResponse[array_search('jxr5000596dev', array_column($databasesResponse, 'name'), true)];
        $selectedDatabase = $databaseResponse;

        if ($multiDb) {
            $databaseResponse2 = $databasesResponse[array_search('profserv2', array_column($databasesResponse, 'name'), true)];
            $databaseBackupsResponse2 = $this->mockDatabaseBackupsResponse($selectedEnvironment, $databaseResponse2->name, 1, $existingBackups);
            $this->mockDownloadBackup($databaseResponse2, $selectedEnvironment, $databaseBackupsResponse2->_embedded->items[0], $curlCode, $validationError);
        }

        $sshHelper = $this->mockSshHelper();
        if ($mockGetAcsfSites) {
            $this->mockGetAcsfSites($sshHelper);
        }

        if ($onDemand) {
            $backupResponse = $this->mockDatabaseBackupCreateResponse($selectedEnvironment, $selectedDatabase->name);
            // Cloud API does not provide the notification UUID as part of the backup response, so we must hardcode it.
            $this->mockNotificationResponseFromObject($backupResponse, $onDemandSuccess);
        }

        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, $mysqlConnectSuccessful);

        if (!$onDemandSuccess) {
            return;
        }

        $databaseBackupsResponse = $this->mockDatabaseBackupsResponse($selectedEnvironment, $databaseResponse->name, 1, $existingBackups);
        $this->mockDownloadBackup($databaseResponse, $selectedEnvironment, $databaseBackupsResponse->_embedded->items[0], $curlCode, $validationError);

        // If there's a validation error, we don't need to mock the rest of the database operations.
        if ($validationError) {
            // Only mock filesystem for errors that need it (not 'missing' which throws before any cleanup).
            if ($validationError !== 'missing') {
                $fs = $this->prophet->prophesize(Filesystem::class);
                $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
                $fs->remove(Argument::type('string'))->shouldBeCalled();
            }
            return;
        }

        $fs = $this->prophet->prophesize(Filesystem::class);
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

    public function testPullDatabasesWithCodebaseUuid(): void
    {
        $codebaseUuid = '11111111-041c-44c7-a486-7972ed2cafc8';
        self::SetEnvVars(['AH_CODEBASE_UUID' =>  $codebaseUuid]);

        // Mock the codebase returned from /codebases/{uuid}.
        $codebase =  $this->getMockCodeBaseResponse();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid)
            ->willReturn($codebase);

        // // Build one codebase environment (so prompt is skipped).
        $codebaseEnv = $this->getMockCodeBaseEnvironment();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/environments')
            ->willReturn([$codebaseEnv])
            ->shouldBeCalled();

        $codeabaseSites = $this->getMockCodeBaseSites();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/sites')
            ->willReturn($codeabaseSites);
        $siteInstance = $this->getMockSiteInstanceResponse();

        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc')
            ->willReturn($siteInstance)
            ->shouldBeCalled();
        $siteId = '8979a8ac-80dc-4df8-b2f0-6be36554a370';
        $site = $this->getMockSite();
        $this->clientProphecy->request('get', '/sites/' . $siteId)
            ->willReturn($site)
            ->shouldBeCalled();
        $siteInstanceDatabase = $this->getMockSiteInstanceDatabaseResponse();
        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database')
            ->willReturn($siteInstanceDatabase)
            ->shouldBeCalled();
        $createSiteInstanceDatabaseBackup = $this->getMockSiteInstanceDatabaseBackupsResponse('post', '201');
        $this->clientProphecy->request('post', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database/backups')
            ->willReturn($createSiteInstanceDatabaseBackup);
        $siteInstanceDatabaseBackups = $this->getMockSiteInstanceDatabaseBackupsResponse();
        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database/backups')
            ->willReturn($siteInstanceDatabaseBackups->_embedded->items)
            ->shouldBeCalled();

        $url = "https://environment-service-php.acquia.com/api/environments/d3f7270e-c45f-4801-9308-5e8afe84a323/";
        $this->mockDownloadCodebaseBackup(EnvironmentTransformer::transformSiteInstanceDatabase(new SiteInstanceDatabaseResponse($siteInstanceDatabase)), $url, EnvironmentTransformer::transformSiteInstanceDatabaseBackup(new SiteInstanceDatabaseBackupResponse($siteInstanceDatabaseBackups->_embedded->items[0])));

        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'example');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, true, true, 'example', 'dbexample', 'example', 'environment_3e8ecbec-ea7c-4260-8414-ef2938c859bc', '2025-04-01T13:01:06.603Z');
        $fs->remove(Argument::type('string'))->shouldBeCalled();
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        // $this->mockExecuteDrushExists($localMachineHelper);
        // $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        // $process = $this->mockProcess();
        // $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);
        // $this->mockExecuteDrushSqlSanitize($localMachineHelper, $process);
        $inputs = self::inputChooseEnvironment();

        $this->executeCommand([
            '--no-scripts' => true,
            '--on-demand' => false,
        ], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Detected Codebase UUID:', $output);
        $this->assertStringContainsString('Using codebase: Test codebase with attached application', $output);
        $this->assertStringContainsString('Connecting to database drupal', $output);

        // NEW: prove that progress actually rendered
        // $this->assertStringContainsString('0/100 [', $output);
        // $this->assertStringContainsString('50/100 [', $output);.
        $this->assertStringContainsString('100/100 [', $output);

        // Verify that the codebase database path was taken (both codebaseUuid AND siteId must be set)
        // This assertion would fail if the LogicalAnd mutation changes the condition.
        $this->assertStringNotContainsString('Choose a database', $output);

        self::unsetEnvVars(['AH_CODEBASE_UUID']);
    }

    /**
     * CRITICAL: Test that kills the LogicalAnd (&&) -> LogicalOr (||) mutation in determineCloudDatabases.
     *
     * This test creates a scenario where $this->siteId is set but codebaseUuid is null.
     * With && (correct): null && "value" = false, so regular ACSF path is used
     * With || (mutant): null || "value" = true, so codebase path is attempted
     *
     * The mutant MUST fail because it would call getSiteInstanceDatabase() without
     * the required codebase-specific API mocks, causing the test to fail.
     */
    public function testPullDatabasesKillsLogicalAndMutation(): void
    {
        // CRITICAL: Ensure codebaseUuid is null/empty.
        self::unsetEnvVars(['AH_CODEBASE_UUID']);

        // Set siteId using reflection to simulate the || mutation scenario.
        $reflection = new \ReflectionClass($this->command);
        $siteIdProperty = $reflection->getProperty('siteId');
        $siteIdProperty->setValue($this->command, '8979a8ac-80dc-4df8-b2f0-6be36554a370');

        // IMPORTANT: Only mock ACSF database calls, NOT codebase-specific calls
        // This ensures the mutant would fail when trying to call getSiteInstanceDatabase.
        $this->setupPullDatabase(true, true, false, false);
        $inputs = self::inputChooseEnvironment();

        // If mutation is active (||), this would fail because getSiteInstanceDatabase
        // would be called without proper mocks for /site-instances/* endpoints.
        $this->executeCommand([
            '--no-scripts' => true,
            'site' => 'jxr5000596dev',
        ], $inputs);

        $output = $this->getDisplay();

        // These assertions prove the ACSF path was used (not codebase path)
        $this->assertStringNotContainsString('Detected Codebase UUID', $output);
        $this->assertStringContainsString('Downloading', $output);

        // The mutant (||) would cause getSiteInstanceDatabase() to be called,
        // which would fail due to missing API mocks.
    }

    public function testPullDatabasesWithCodebaseUuidOnDemand(): void
    {
        $codebaseUuid = '11111111-041c-44c7-a486-7972ed2cafc8';
        self::SetEnvVars(['AH_CODEBASE_UUID' =>  $codebaseUuid]);

        // Mock the codebase returned from /codebases/{uuid}.
        $codebase =  $this->getMockCodeBaseResponse();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid)
            ->willReturn($codebase);

        // // Build one codebase environment (so prompt is skipped).
        $codebaseEnv = $this->getMockCodeBaseEnvironment();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/environments')
            ->willReturn([$codebaseEnv])
            ->shouldBeCalled();

        $codeabaseSites = $this->getMockCodeBaseSites();
        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid . '/sites')
            ->willReturn($codeabaseSites);
        $siteInstance = $this->getMockSiteInstanceResponse();

        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc')
            ->willReturn($siteInstance)
            ->shouldBeCalled();
        $siteId = '8979a8ac-80dc-4df8-b2f0-6be36554a370';
        $site = $this->getMockSite();
        $this->clientProphecy->request('get', '/sites/' . $siteId)
            ->willReturn($site)
            ->shouldBeCalled();
        $siteInstanceDatabase = $this->getMockSiteInstanceDatabaseResponse();
        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database')
            ->willReturn($siteInstanceDatabase)
            ->shouldBeCalled();
        $createSiteInstanceDatabaseBackup = $this->getMockSiteInstanceDatabaseBackupsResponse('post', '201');
        $this->clientProphecy->request('post', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database/backups')
            ->willReturn($createSiteInstanceDatabaseBackup)
            ->shouldBeCalled();
        ;
        $siteInstanceDatabaseBackups = $this->getMockSiteInstanceDatabaseBackupsResponse();
        $this->clientProphecy->request('get', '/site-instances/8979a8ac-80dc-4df8-b2f0-6be36554a370.3e8ecbec-ea7c-4260-8414-ef2938c859bc/database/backups')
            ->willReturn($siteInstanceDatabaseBackups->_embedded->items)
            ->shouldBeCalled();

        $url = "https://environment-service-php.acquia.com/api/environments/d3f7270e-c45f-4801-9308-5e8afe84a323/";
        $this->mockDownloadCodebaseBackup(EnvironmentTransformer::transformSiteInstanceDatabase(new SiteInstanceDatabaseResponse($siteInstanceDatabase)), $url, EnvironmentTransformer::transformSiteInstanceDatabaseBackup(new SiteInstanceDatabaseBackupResponse($siteInstanceDatabaseBackups->_embedded->items[0])));

        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteMySqlConnect($localMachineHelper, true);
        $this->mockExecuteMySqlListTables($localMachineHelper, 'example');
        $fs = $this->prophet->prophesize(Filesystem::class);
        $this->mockExecuteMySqlDropDb($localMachineHelper, true, $fs);
        $this->mockExecuteMySqlImport($localMachineHelper, true, true, 'example', 'dbexample', 'example', 'environment_3e8ecbec-ea7c-4260-8414-ef2938c859bc', '2025-04-01T13:01:06.603Z');
        $fs->remove(Argument::type('string'))->shouldBeCalled();
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
        // $this->mockExecuteDrushExists($localMachineHelper);
        // $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        // $process = $this->mockProcess();
        // $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);
        // $this->mockExecuteDrushSqlSanitize($localMachineHelper, $process);
        $inputs = self::inputChooseEnvironment();

        $this->executeCommand([
            '--no-scripts' => true,
            '--on-demand' => true,
        ], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Detected Codebase UUID:', $output);
        $this->assertStringContainsString('Using codebase: Test codebase with attached application', $output);
        $this->assertStringContainsString('Connecting to database drupal', $output);

        // NEW: prove that progress actually rendered
        // $this->assertStringContainsString('0/100 [', $output);
        // $this->assertStringContainsString('50/100 [', $output);.
        $this->assertStringContainsString('100/100 [', $output);

        // Verify that the codebase database path was taken (both codebaseUuid AND siteId must be set)
        // This assertion would fail if the LogicalAnd mutation changes the condition.
        $this->assertStringNotContainsString('Choose a database', $output);

        self::unsetEnvVars(['AH_CODEBASE_UUID']);
    }

    /**
     * Test that kills the PublicVisibility mutant for getBackupPath static method.
     * This test ensures the static method remains public and accessible from outside the class.
     */
    public function testPublicVisibilityMutantKillerForGetBackupPath(): void
    {
        // Ensure the method is public and accessible from outside the class.
        $reflection = new \ReflectionMethod(PullCommandBase::class, 'getBackupPath');
        $this->assertTrue($reflection->isPublic(), 'getBackupPath method must remain public');

        // Create mock objects for testing the static method call.
        $environment = (object) ['name' => 'test-env'];

        // Create a proper DatabaseResponse object with all required properties.
        $databaseData = (object) [
            'db_host' => 'localhost',
            'environment' => (object) ['name' => 'test-env'],
            'flags' => (object) ['default' => true],
            'id' => '123',
            'name' => 'test_db',
            'password' => null,
            'ssh_host' => null,
            'url' => null,
            'user_name' => null,
        ];
        $database = new DatabaseResponse($databaseData);

        $backupResponse = (object) ['completedAt' => '2023-01-01T12:00:00.000Z'];

        // This call must work - if the method becomes protected, this will fail.
        $backupPath = PullCommandBase::getBackupPath($environment, $database, $backupResponse);

        $this->assertIsString($backupPath, 'getBackupPath must return a string');
        $this->assertStringContainsString('test-env', $backupPath, 'Backup path must contain environment name');
        $this->assertStringContainsString('test_db', $backupPath, 'Backup path must contain database name');
        $this->assertStringContainsString('.sql.gz', $backupPath, 'Backup path must have .sql.gz extension');
    }

    /**
     * Test that kills the MethodCallRemoval mutant for displayDownloadProgress static method call.
     * This test ensures the static method call cannot be removed without breaking functionality.
     */
    public function testMethodCallRemovalMutantKillerForDisplayDownloadProgress(): void
    {
        $output = new BufferedOutput();
        $progress = null;

        PullCommandBase::displayDownloadProgress(100, 0, $progress, $output);
        $this->assertStringContainsString('0/100', $output->fetch(), 'Initial progress display must work');

        sleep(1);

        PullCommandBase::displayDownloadProgress(100, 50, $progress, $output);

        $displayedOutput = $output->fetch();
        $this->assertStringContainsString('50/100', $displayedOutput, 'displayDownloadProgress static method call must not be removed');
        $this->assertStringContainsString('50%', $displayedOutput, 'Progress percentage must be displayed');
        $this->assertStringContainsString('💧', $displayedOutput, 'Progress indicator must be displayed');
    }
}
