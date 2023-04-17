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
 * Class AliasesDownloadCommandTest.
 *
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
   * Tests the 'remote:aliases:download' command.
   *
   * @param array $inputs
   * @param array $args
   * @param string|null $destination_dir
   * @param bool $all
   *   Download aliases for all applications.
   * @dataProvider providerTestRemoteAliasesDownloadCommand
   */
  public function testRemoteAliasesDownloadCommand(array $inputs, array $args, string $destination_dir = NULL, bool $all = FALSE): void {
    $alias_version = $inputs[0];

    $drush_aliases_fixture = Path::canonicalize(__DIR__ . '/../../../../fixtures/drush-aliases');
    $drush_aliases_tarball_fixture_filepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
    $archive_fixture = new PharData($drush_aliases_tarball_fixture_filepath . '.tar');
    $archive_fixture->buildFromDirectory($drush_aliases_fixture);
    $archive_fixture->compress(Phar::GZ);

    $stream = Utils::streamFor(file_get_contents($drush_aliases_tarball_fixture_filepath . '.tar.gz'));
    $this->clientProphecy->addQuery('version', $alias_version);
    $this->clientProphecy->stream('get', '/account/drush-aliases/download')->willReturn($stream);
    $drush_archive_filepath = $this->command->getDrushArchiveTempFilepath();

    $destination_dir = $destination_dir ?? Path::join($this->acliRepoRoot, 'drush');
    if ($alias_version === '8') {
      $home_dir = $this->getTempDir();
      putenv('HOME=' . $home_dir);
      $destination_dir = Path::join($home_dir, '.drush');
    }
    if ($alias_version === '9' && !$all) {
      $applications_response = $this->getMockResponseFromSpec('/applications', 'get', '200');
      $cloud_application = $applications_response->{'_embedded'}->items[0];
      $cloud_application_uuid = $cloud_application->uuid;
      $this->createMockAcliConfigFile($cloud_application_uuid);
      $this->mockApplicationRequest();
    }

    $this->executeCommand($args, $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertFileDoesNotExist($drush_archive_filepath);
    $this->assertFileExists($destination_dir);
    $this->assertStringContainsString('Cloud Platform Drush aliases installed into ' . $destination_dir, $output);

  }

}
