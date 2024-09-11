<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Remote\AliasesDownloadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use GuzzleHttp\Psr7\Utils;
use Phar;
use PharData;
use Symfony\Component\Filesystem\Path;

/**
 * @property AliasesDownloadCommand $command
 */
class AliasesDownloadCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AliasesDownloadCommand::class);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
        $this->command = $this->createCommand();
    }

    /**
     * Test all Drush alias versions.
     *
     * @return array<array<mixed>>
     */
    public function providerTestRemoteAliasesDownloadCommand(): array
    {
        return [
            [['9'], []],
            [['9'], ['--destination-dir' => 'testdir'], 'testdir'],
            [['9'], ['--all' => true], null, true],
            [['8'], []],
        ];
    }

    /**
     * @param string|null $destinationDir
     * @param bool $all Download aliases for all applications.
     * @dataProvider providerTestRemoteAliasesDownloadCommand
     */
    public function testRemoteAliasesDownloadCommand(array $inputs, array $args, string $destinationDir = null, bool $all = false): void
    {
        $aliasVersion = $inputs[0];

        $drushAliasesFixture = Path::canonicalize(__DIR__ . '/../../../../fixtures/drush-aliases');
        $drushAliasesTarballFixtureFilepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
        $archiveFixture = new PharData($drushAliasesTarballFixtureFilepath . '.tar');
        $archiveFixture->buildFromDirectory($drushAliasesFixture);
        $archiveFixture->compress(Phar::GZ);

        $stream = Utils::streamFor(file_get_contents($drushAliasesTarballFixtureFilepath . '.tar.gz'));
        $this->clientProphecy->addQuery('version', $aliasVersion);
        $this->clientProphecy->stream('get', '/account/drush-aliases/download')
            ->willReturn($stream);
        $drushArchiveFilepath = $this->command->getDrushArchiveTempFilepath();

        $destinationDir = $destinationDir ?? Path::join($this->acliRepoRoot, 'drush');
        if ($aliasVersion === '8') {
            $homeDir = $this->getTempDir();
            putenv('HOME=' . $homeDir);
            $destinationDir = Path::join($homeDir, '.drush');
        }
        if ($aliasVersion === '9' && !$all) {
            $applicationsResponse = $this->getMockResponseFromSpec('/applications', 'get', '200');
            $cloudApplication = $applicationsResponse->{'_embedded'}->items[0];
            $cloudApplicationUuid = $cloudApplication->uuid;
            $this->createMockAcliConfigFile($cloudApplicationUuid);
            $this->createDataStores();
            $this->command = $this->injectCommand(AliasesDownloadCommand::class);
            $this->mockApplicationRequest();
        }

        $this->executeCommand($args, $inputs);

        // Assert.
        $output = $this->getDisplay();

        $this->assertFileDoesNotExist($drushArchiveFilepath);
        $this->assertFileExists($destinationDir);
        $this->assertStringContainsString('Cloud Platform Drush aliases installed into ' . $destinationDir, $output);
    }

    /**
     * @requires OS linux|darwin
     */
    public function testRemoteAliasesDownloadFailed(): void
    {
        $drushAliasesFixture = Path::canonicalize(__DIR__ . '/../../../../fixtures/drush-aliases');
        $drushAliasesTarballFixtureFilepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
        $archiveFixture = new PharData($drushAliasesTarballFixtureFilepath . '.tar');
        $archiveFixture->buildFromDirectory($drushAliasesFixture);
        $archiveFixture->compress(Phar::GZ);

        $stream = Utils::streamFor(file_get_contents($drushAliasesTarballFixtureFilepath . '.tar.gz'));
        $this->clientProphecy->addQuery('version', '9');
        $this->clientProphecy->stream('get', '/account/drush-aliases/download')
            ->willReturn($stream);

        $destinationDir = Path::join($this->acliRepoRoot, 'drush');
        $sitesDir = Path::join($destinationDir, 'sites');
        mkdir($sitesDir, 0777, true);
        chmod($sitesDir, 000);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage("Could not extract aliases to $destinationDir");
        $this->executeCommand([
            '--all' => true,
            '--destination-dir' => $destinationDir,
        ], ['9']);
    }
}
