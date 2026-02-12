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
        ?string $dir = null
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
        $localMachineHelper->execute($command, Argument::type('callable'), null, Argument::any())
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
        string $env = 'dev',
        string $createdAt = '2012-05-15T12:00:00.000Z'
    ): void {
        $localMachineHelper->checkRequiredBinariesExist(['gunzip', 'mysql'])
            ->shouldBeCalled();
        $this->mockExecutePvExists($localMachineHelper, $pvExists);
        $process = $this->mockProcess($success);
        $filePath = Path::join(sys_get_temp_dir(), "$env-$dbName-$dbMachineName-$createdAt.sql.gz");
        $command = $pvExists ? 'pv "${:LOCAL_DUMP_FILEPATH}" --bytes --rate | gunzip | MYSQL_PWD="${:MYSQL_PASSWORD}" mysql --host="${:MYSQL_HOST}" --user="${:MYSQL_USER}" "${:MYSQL_DATABASE}"' : 'gunzip -c "${:LOCAL_DUMP_FILEPATH}" | MYSQL_PWD="${:MYSQL_PASSWORD}" mysql --host="${:MYSQL_HOST}" --user="${:MYSQL_USER}" "${:MYSQL_DATABASE}"';
        // MySQL import command.
        $localMachineHelper
            ->executeFromCmd(
                $command,
                Argument::any(),
                null,
                Argument::that(function ($printOutput) {
                    return $printOutput === false;
                }),
                null,
                Argument::that(function ($envVars) use ($localDbName) {
                    // On Windows, the filepath is in 8.3 format (hashed),
                    // so we can't do strict matching. We just verify that
                    // the required environment variables exist with expected values.
                    if (!is_array($envVars)) {
                        return false;
                    }
                    // Check required env vars exist (values vary by platform for LOCAL_DUMP_FILEPATH)
                    if (!array_key_exists('LOCAL_DUMP_FILEPATH', $envVars)) {
                        return false;
                    }
                    // Verify the filepath ends with expected suffix and is a valid gzip file.
                    if (!str_ends_with($envVars['LOCAL_DUMP_FILEPATH'], '.sql.gz')) {
                        return false;
                    }
                    // Verify other required env vars.
                    if (!array_key_exists('MYSQL_DATABASE', $envVars) || $envVars['MYSQL_DATABASE'] !== $localDbName) {
                        return false;
                    }
                    if (!array_key_exists('MYSQL_HOST', $envVars) || $envVars['MYSQL_HOST'] !== 'localhost') {
                        return false;
                    }
                    if (!array_key_exists('MYSQL_PASSWORD', $envVars) || $envVars['MYSQL_PASSWORD'] !== 'drupal') {
                        return false;
                    }
                    if (!array_key_exists('MYSQL_USER', $envVars) || $envVars['MYSQL_USER'] !== 'drupal') {
                        return false;
                    }
                    return true;
                })
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

    public function mockGetBackup(mixed $environment): void
    {
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

    protected function mockDownloadBackup(object $database, object $environment, object $backup, int $curlCode = 0, string $validationError = ''): object
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
            $domainsResponse = self::getMockResponseFromSpec('/environments/{environmentId}/domains', 'get', 200);
            $this->clientProphecy->request('get', "/environments/$environment->id/domains")
                ->willReturn($domainsResponse->_embedded->items);
            $method = new \ReflectionMethod($this->command, 'setBackupDownloadUrl');
            $method->invoke($this->command, new Uri('https://www.example.com/download-backup'));
        } else {
            $this->mockDownloadBackupResponse($environment, $database->name, 1);
        }
        if ($database->flags->default) {
            $dbMachineName = $database->name . $environment->name;
        } else {
            $dbMachineName = 'db' . $database->id;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            // Use short filename to comply with 8.3 format and avoid long path issues.
            $hash = substr(md5($environment->name . $database->name . $dbMachineName . $backup->completedAt), 0, 8);
            $filename = $hash . '.sql.gz';
        } else {
            $completedAtFormatted = $backup->completedAt;
            $filename = implode('-', [
                $environment->name,
                $database->name,
                $dbMachineName,
                $completedAtFormatted,
            ]) . '.sql.gz';
        }
        $localFilepath = Path::join(sys_get_temp_dir(), $filename);

        // Create file based on validation error type.
        switch ($validationError) {
            case 'missing':
                // Don't create a file to test missing file validation.
                if (file_exists($localFilepath)) {
                    unlink($localFilepath);
                }
                break;
            default:
                // Create a valid gzip file for normal testing.
                $content = 'Mock SQL dump content for testing';
                $gzippedContent = gzencode($content);
                file_put_contents($localFilepath, $gzippedContent);
                break;
        }

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

    protected function mockDownloadCodebaseBackup(object $database, string $url, object $backup, int $curlCode = 0, string $validationError = '', int $httpStatusCode = 0): object
    {
        // Calculate dbMachineName the same way as getBackupPath.
        $environment = (object) ['name' => 'environment_3e8ecbec-ea7c-4260-8414-ef2938c859bc'];
        if (isset($database->flags->default) && $database->flags->default) {
            $dbMachineName = $database->name . $environment->name;
        } else {
            $dbMachineName = 'db' . ($database->id ?? 'example');
        }

        $completedAtFormatted = $backup->completedAt ?? '2025-04-01T13:01:06.603Z';

        // Use the same filename generation logic as getBackupPath() to ensure consistency.
        // On Windows, use short filename to comply with 8.3 format and avoid long path issues.
        if (PHP_OS_FAMILY === 'Windows') {
            $hash = substr(md5($environment->name . ($database->name ?? 'example') . $dbMachineName . $completedAtFormatted), 0, 8);
            $filename = $hash . '.sql.gz';
        } else {
            $filename = implode('-', [
                $environment->name,
                $database->name ?? 'example',
                $dbMachineName,
                $completedAtFormatted,
            ]) . '.sql.gz';
        }
        $localFilepath = Path::join(sys_get_temp_dir(), $filename);

        // Cloud API client options are always set first.
        $this->clientProphecy->addOption('sink', $localFilepath)->shouldBeCalled();
        $this->clientProphecy->addOption('curl.options', [
            'CURLOPT_FILE' => $localFilepath,
            'CURLOPT_RETURNTRANSFER' => false,
        ])->shouldBeCalled();

        $this->clientProphecy
            ->addOption('progress', Argument::that(static fn($v) => is_callable($v)))
            ->shouldBeCalled();
        $this->clientProphecy
            ->addOption('on_stats', Argument::that(static fn($v) => is_callable($v)))
            ->shouldBeCalled();

        // Mock the HTTP client request for codebase downloads.
        $downloadUrl = $backup->links->download->href ?? 'https://example.com/download-backup';
        // Allow explicit HTTP status code override; otherwise infer from validationError.
        if ($httpStatusCode !== 0) {
            $statusCode = $httpStatusCode;
        } else {
            $statusCode = $validationError === 'http_error' ? 500 : 200;
        }
        $response = new \GuzzleHttp\Psr7\Response($statusCode);

        $capturedOpts = null;
        $this->httpClientProphecy
            ->request(
                'GET',
                $downloadUrl,
                Argument::that(function (array $opts) use (&$capturedOpts, $localFilepath): bool {
                    $capturedOpts = $opts;

                    // Enforce the presence & types we care about.
                    if (!isset($opts['sink']) || !is_string($opts['sink'])) {
                        return false;
                    }
                    if ($opts['sink'] !== $localFilepath) {
                        return false;
                    }
                    if (!isset($opts['progress']) || !is_callable($opts['progress'])) {
                        return false;
                    }
                    return true;
                })
            )
            ->will(function () use (&$capturedOpts, $response, $localFilepath, $validationError): \Psr\Http\Message\ResponseInterface {
                // Create file based on validation error type.
                switch ($validationError) {
                    case 'missing':
                        // Don't create a file to test missing file validation.
                        if (file_exists($localFilepath)) {
                            unlink($localFilepath);
                        }
                        break;
                    case 'http_error':
                        // For HTTP error, create file that will be cleaned up.
                        $content = 'Mock SQL dump content for testing';
                        $gzippedContent = gzencode($content);
                        if ($gzippedContent !== false) {
                            file_put_contents($localFilepath, $gzippedContent);
                        }
                        break;
                    default:
                        // Create a valid gzip file for validation.
                        $content = 'Mock SQL dump content for testing';
                        $gzippedContent = gzencode($content);
                        if ($gzippedContent !== false) {
                            file_put_contents($localFilepath, $gzippedContent);
                        }
                        break;
                }

                // Simulate the download to force progress rendering.
                if (isset($capturedOpts['progress'])) {
                    $progress = $capturedOpts['progress'];
                    $progress(100, 0);
                    $progress(100, 50);
                    $progress(100, 100);
                }
                return $response;
            })
            ->shouldBeCalled();

        return $database;
    }
}
