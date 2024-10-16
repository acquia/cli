<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\BackupsResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

abstract class PullCommandTestBase extends CommandTestBase
{
    use IdeRequiredTestTrait;

    protected Client|ObjectProphecy $httpClientProphecy;

    protected string $dbUser = 'drupal';

    protected string $dbPassword = 'drupal';

    protected string $dbHost = 'localhost';

    protected string $dbName = 'drupal';

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

    protected function mockExecuteDrushExists(
        ObjectProphecy $localMachineHelper
    ): void {
        $localMachineHelper
            ->commandExists('drush')
            ->willReturn(true)
            ->shouldBeCalled();
    }

    protected function mockExecuteDrushStatus(
        ObjectProphecy $localMachineHelper,
        string $dir = null
    ): void {
        $drushStatusProcess = $this->prophet->prophesize(Process::class);
        $drushStatusProcess->isSuccessful()->willReturn(true);
        $drushStatusProcess->getExitCode()->willReturn(0);
        $drushStatusProcess->getOutput()
            ->willReturn(json_encode(['db-status' => 'Connected']));
        $localMachineHelper
            ->execute([
                'drush',
                'status',
                '--fields=db-status,drush-version',
                '--format=json',
                '--no-interaction',
            ], Argument::any(), $dir, false)
            ->willReturn($drushStatusProcess->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteDrushCacheRebuild(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process
    ): void {
        $localMachineHelper
            ->execute([
                'drush',
                'cache:rebuild',
                '--yes',
                '--no-interaction',
                '--verbose',
            ], Argument::type('callable'), $this->projectDir, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteDrushSqlSanitize(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process
    ): void {
        $localMachineHelper
            ->execute([
                'drush',
                'sql:sanitize',
                '--yes',
                '--no-interaction',
                '--verbose',
            ], Argument::type('callable'), $this->projectDir, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteComposerExists(
        ObjectProphecy $localMachineHelper
    ): void {
        $localMachineHelper
            ->commandExists('composer')
            ->willReturn(true)
            ->shouldBeCalled();
    }

    protected function mockExecuteComposerInstall(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process
    ): void {
        $localMachineHelper
            ->execute([
                'composer',
                'install',
                '--no-interaction',
            ], Argument::type('callable'), $this->projectDir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockDrupalSettingsRefresh(
        ObjectProphecy $localMachineHelper
    ): void {
        $localMachineHelper
            ->execute([
                '/ide/drupal-setup.sh',
            ]);
    }

    protected function mockExecuteGitStatus(
        mixed $failed,
        ObjectProphecy $localMachineHelper,
        mixed $cwd
    ): void {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(!$failed)->shouldBeCalled();
        $localMachineHelper->executeFromCmd('git add . && git diff-index --cached --quiet HEAD', null, $cwd, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockGetLocalCommitHash(
        ObjectProphecy $localMachineHelper,
        mixed $cwd,
        mixed $commitHash
    ): void {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true)->shouldBeCalled();
        $process->getOutput()->willReturn($commitHash)->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'rev-parse',
            'HEAD',
        ], null, $cwd, false)->willReturn($process->reveal())->shouldBeCalled();
    }

    protected function mockFinder(): ObjectProphecy
    {
        $finder = $this->prophet->prophesize(Finder::class);
        $finder->files()->willReturn($finder);
        $finder->in(Argument::type('string'))->willReturn($finder);
        $finder->in(Argument::type('array'))->willReturn($finder);
        $finder->ignoreDotFiles(false)->willReturn($finder);
        $finder->ignoreVCS(false)->willReturn($finder);
        $finder->ignoreVCSIgnored(true)->willReturn($finder);
        $finder->hasResults()->willReturn(true);
        $finder->name(Argument::type('string'))->willReturn($finder);
        $finder->notName(Argument::type('string'))->willReturn($finder);
        $finder->directories()->willReturn($finder);
        $finder->append(Argument::type(Finder::class))->willReturn($finder);

        return $finder;
    }

    protected function mockExecuteGitFetchAndCheckout(
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process,
        string $cwd,
        string $vcsPath
    ): void {
        $localMachineHelper->execute([
            'git',
            'fetch',
            '--all',
        ], Argument::type('callable'), $cwd, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $this->mockExecuteGitCheckout($localMachineHelper, $vcsPath, $cwd, $process);
    }

    protected function mockExecuteGitCheckout(ObjectProphecy $localMachineHelper, string $vcsPath, string $cwd, ObjectProphecy $process): void
    {
        $localMachineHelper->execute([
            'git',
            'checkout',
            $vcsPath,
        ], Argument::type('callable'), $cwd, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteRsync(
        LocalMachineHelper|ObjectProphecy $localMachineHelper,
        mixed $environment,
        string $sourceDir,
        string $destinationDir
    ): void {
        $process = $this->mockProcess();
        $localMachineHelper->checkRequiredBinariesExist(['rsync'])
            ->shouldBeCalled();
        $command = [
            'rsync',
            '-avPhze',
            'ssh -o StrictHostKeyChecking=no',
            $environment->ssh_url . ':' . $sourceDir,
            $destinationDir,
        ];
        $localMachineHelper->execute($command, Argument::type('callable'), null, true)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteMySqlConnect(
        ObjectProphecy $localMachineHelper,
        bool $success
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(["mysql"])
            ->shouldBeCalled();
        $process = $this->mockProcess($success);
        $localMachineHelper
            ->execute([
                'mysql',
                '--host',
                $this->dbHost,
                '--user',
                'drupal',
                'drupal',
            ], Argument::type('callable'), null, false, null, ['MYSQL_PWD' => 'drupal'])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteMySqlListTables(
        LocalMachineHelper|ObjectProphecy $localMachineHelper,
        string $dbName = 'jxr5000596dev',
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(["mysql"])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $process->getOutput()->willReturn('table1');
        $command = [
            'mysql',
            '--host',
            'localhost',
            '--user',
            'drupal',
            $dbName,
            '--silent',
            '-e',
            'SHOW TABLES;',
        ];
        $localMachineHelper
            ->execute($command, Argument::type('callable'), null, false, null, ['MYSQL_PWD' => $this->dbPassword])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteMySqlDropDb(
        LocalMachineHelper|ObjectProphecy $localMachineHelper,
        bool $success,
        ObjectProphecy $fs
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(["mysql"])
            ->shouldBeCalled();
        $process = $this->mockProcess($success);
        $fs->tempnam(Argument::type('string'), 'acli_drop_table_', '.sql')
            ->willReturn('something')
            ->shouldBeCalled();
        $fs->dumpfile('something', Argument::type('string'))->shouldBeCalled();
        $localMachineHelper
            ->execute(Argument::type('array'), Argument::type('callable'), null, false, null, ['MYSQL_PWD' => $this->dbPassword])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteMySqlImport(
        ObjectProphecy $localMachineHelper,
        bool $success,
        bool $pvExists,
        string $dbName = 'jxr5000596dev',
        string $dbMachineName = 'db554675',
        string $localDbName = 'jxr5000596dev',
        string $env = 'dev'
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(['gunzip', 'mysql'])
            ->shouldBeCalled();
        $this->mockExecutePvExists($localMachineHelper, $pvExists);
        $process = $this->mockProcess($success);
        $filePath = Path::join(sys_get_temp_dir(), "$env-$dbName-$dbMachineName-2012-05-15T12:00:00.000Z.sql.gz");
        $command = $pvExists ? "pv $filePath --bytes --rate | gunzip | MYSQL_PWD=drupal mysql --host=localhost --user=drupal $localDbName" : "gunzip -c $filePath | MYSQL_PWD=drupal mysql --host=localhost --user=drupal $localDbName";
        // MySQL import command.
        $localMachineHelper
            ->executeFromCmd(
                $command,
                Argument::type('callable'),
                null,
                true,
                null
            )
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockDownloadMySqlDump(ObjectProphecy $localMachineHelper, mixed $success): void
    {
        $this->mockProcess($success);
        $localMachineHelper->writeFile(
            Argument::containingString("dev-profserv2-profserv201dev-something.sql.gz"),
            'backupfilecontents'
        )
            ->shouldBeCalled();
    }

    protected function mockSettingsFiles(ObjectProphecy $fs): void
    {
        $fs->remove(Argument::type('string'))
            ->willReturn()
            ->shouldBeCalled();
    }

    protected function mockListSites(SshHelper|ObjectProphecy $sshHelper): void
    {
        $process = $this->mockProcess();
        $process->getOutput()->willReturn('default')->shouldBeCalled();
        $sshHelper->executeCommand(Argument::type('string'), [
            'ls',
            '/mnt/files/site.dev/sites',
        ], false)
            ->willReturn($process->reveal())->shouldBeCalled();
    }

    public function mockGetBackup(mixed $environment, bool $perms = true): void
    {
        if (!$perms) {
            $tamper = static function ($databases): void {
                $databases[0]->user_name = null;
            };
            $this->mockRequest('getEnvironmentsDatabases', $environment->id, null, null, $tamper);
            return;
        }
        $databases = $this->mockRequest('getEnvironmentsDatabases', $environment->id);
        $tamper = static function ($backups): void {
            $backups[0]->completedAt = $backups[0]->completed_at;
        };
        $backups = new BackupsResponse(
            $this->mockRequest('getEnvironmentsDatabaseBackups', [
                $environment->id,
                'my_db',
            ], null, null, $tamper)
        );
        $this->mockDownloadBackup($databases[0], $environment, $backups[0]);
    }

    protected function mockDownloadBackup(object $database, object $environment, object $backup, int $curlCode = 0): object
    {
        if ($curlCode) {
            $this->prophet->prophesize(StreamInterface::class);
            /** @var RequestException|ObjectProphecy $requestException */
            $requestException = $this->prophet->prophesize(RequestException::class);
            $requestException->getHandlerContext()
                ->willReturn(['errno' => $curlCode]);
            $this->clientProphecy->stream('get', "/environments/$environment->id/databases/$database->name/backups/1/actions/download", [])
                ->willThrow($requestException->reveal())
                ->shouldBeCalled();
            $response = $this->prophet->prophesize(ResponseInterface::class);
            $this->httpClientProphecy->request('GET', 'https://other.example.com/download-backup', Argument::type('array'))
                ->willReturn($response->reveal())
                ->shouldBeCalled();
            $domainsResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/domains', 'get', 200);
            $this->clientProphecy->request('get', "/environments/$environment->id/domains")
                ->willReturn($domainsResponse->_embedded->items);
            $this->command->setBackupDownloadUrl(new Uri('https://www.example.com/download-backup'));
        } else {
            $this->mockDownloadBackupResponse($environment, $database->name, 1);
        }
        if ($database->flags->default) {
            $dbMachineName = $database->name . $environment->name;
        } else {
            $dbMachineName = 'db' . $database->id;
        }
        $filename = implode('-', [
            $environment->name,
            $database->name,
            $dbMachineName,
            $backup->completedAt,
        ]) . '.sql.gz';
        $localFilepath = Path::join(sys_get_temp_dir(), $filename);
        $this->clientProphecy->addOption('sink', $localFilepath)
            ->shouldBeCalled();
        $this->clientProphecy->addOption('curl.options', [
            'CURLOPT_FILE' => $localFilepath,
            'CURLOPT_RETURNTRANSFER' => false,
        ])->shouldBeCalled();
        $this->clientProphecy->addOption('progress', Argument::type('Closure'))
            ->shouldBeCalled();
        $this->clientProphecy->addOption('on_stats', Argument::type('Closure'))
            ->shouldBeCalled();
        $this->clientProphecy->getOptions()->willReturn([]);

        return $database;
    }
}
