<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\AliasesDownloadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use GuzzleHttp\Psr7\Utils;
use Phar;
use PharData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;

/**
 * @property AliasesDownloadCommand $command
 */
class AliasesDownloadCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(AliasesDownloadCommand::class);
  }

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
    $this->command = $this->createCommand();
  }

  /**
   * Test all Drush alias versions.
   *
   * @return array<array<mixed>>
   */
  public function providerTestRemoteAliasesDownloadCommand(): array {
    return [
      [['9'], []],
      [['9'], ['--destination-dir' => 'testdir'], 'testdir'],
      [['9'], ['--all' => TRUE], NULL, TRUE],
      [['8'], []],
    ];
  }

  /**
   * @param array $inputs
   * @param array $args
   * @param string|null $destinationDir
   * @param bool $all
   *   Download aliases for all applications.
   * @dataProvider providerTestRemoteAliasesDownloadCommand
   */
  public function testRemoteAliasesDownloadCommand(array $inputs, array $args, string $destinationDir = NULL, bool $all = FALSE): void {
    $aliasVersion = $inputs[0];

    $drushAliasesFixture = Path::canonicalize(__DIR__ . '/../../../../fixtures/drush-aliases');
    $drushAliasesTarballFixtureFilepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
    $archiveFixture = new PharData($drushAliasesTarballFixtureFilepath . '.tar');
    $archiveFixture->buildFromDirectory($drushAliasesFixture);
    $archiveFixture->compress(Phar::GZ);

    $stream = Utils::streamFor(file_get_contents($drushAliasesTarballFixtureFilepath . '.tar.gz'));
    $this->clientProphecy->addQuery('version', $aliasVersion);
    $this->clientProphecy->stream('get', '/account/drush-aliases/download')->willReturn($stream);
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
      $this->mockApplicationRequest();
    }

    $this->executeCommand($args, $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertFileDoesNotExist($drushArchiveFilepath);
    $this->assertFileExists($destinationDir);
    $this->assertStringContainsString('Cloud Platform Drush aliases installed into ' . $destinationDir, $output);

  }

}
