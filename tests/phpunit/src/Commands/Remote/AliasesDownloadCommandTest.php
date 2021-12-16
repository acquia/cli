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
 * @package Acquia\Cli\Tests\Remote
 */
class AliasesDownloadCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AliasesDownloadCommand::class);
  }

  /**
   * Test all Drush alias versions.
   */
  public function providerTestRemoteAliasesDownloadCommand(): array {
    return [
      [8, NULL],
      [9, NULL],
      [9, 'testdir'],
    ];
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestRemoteAliasesDownloadCommand
   *
   * Tests the 'remote:aliases:download' commands.
   *
   * @throws \Exception|\Psr\Cache\InvalidArgumentException
   */
  public function testRemoteAliasesDownloadCommand($alias_version, $destination_dir): void {
    $drush_aliases_fixture = Path::canonicalize(__DIR__ . '/../../../../fixtures/drush-aliases');
    $drush_aliases_tarball_fixture_filepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
    $archive_fixture = new PharData($drush_aliases_tarball_fixture_filepath . '.tar');
    $archive_fixture->buildFromDirectory($drush_aliases_fixture);
    $archive_fixture->compress(Phar::GZ);

    $stream = Utils::streamFor(file_get_contents($drush_aliases_tarball_fixture_filepath . '.tar.gz'));
    $this->clientProphecy->addQuery('version', $alias_version);
    $this->clientProphecy->stream('get', '/account/drush-aliases/download')->willReturn($stream);
    $drush_archive_filepath = $this->command->getDrushArchiveTempFilepath();
    $drush_aliases_dir = Path::join(sys_get_temp_dir(), '.drush');
    if ($alias_version === 9) {
      $drush_aliases_dir = Path::join($drush_aliases_dir, 'sites');
      $applications = $this->mockApplicationsRequest();
      $this->mockEnvironmentsRequest($applications);
      $this->mockApplicationRequest();
    }
    if ($destination_dir) {
      $this->command->setDrushAliasesDir($destination_dir);
      $args = ['--destination-dir' => $destination_dir];
    }
    else {
      $this->command->setDrushAliasesDir($drush_aliases_dir);
      $destination_dir = $drush_aliases_dir;
      $args = [];
    }

    $inputs = [$alias_version];
    $this->executeCommand($args, $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertFileDoesNotExist($drush_archive_filepath);
    $this->assertFileExists($destination_dir);
    $this->assertStringContainsString('Cloud Platform Drush aliases installed into ' . $destination_dir, $output);

  }

}
