<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\AliasesDownloadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Phar;
use PharData;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;
use function GuzzleHttp\Psr7\stream_for;

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
    return [[8], [9]];
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestRemoteAliasesDownloadCommand
   *
   * Tests the 'remote:aliases:download' commands.
   *
   * @throws \Exception
   */
  public function testRemoteAliasesDownloadCommand($alias_version): void {
    $drush_aliases_fixture = Path::canonicalize(__DIR__ . '/../../../../fixtures/drush-aliases');
    $drush_aliases_tarball_fixture_filepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
    $archive_fixture = new PharData($drush_aliases_tarball_fixture_filepath . '.tar');
    $archive_fixture->buildFromDirectory($drush_aliases_fixture);
    $archive_fixture->compress(Phar::GZ);

    $stream = stream_for(file_get_contents($drush_aliases_tarball_fixture_filepath . '.tar.gz'));
    $this->clientProphecy->addQuery('version', $alias_version);
    $this->clientProphecy->request('get', '/account/drush-aliases/download')->willReturn($stream);
    $drush_archive_filepath = $this->command->getDrushArchiveTempFilepath();
    $drush_aliases_dir = Path::join(sys_get_temp_dir(), '.drush');
    if ($alias_version == 9) {
      $drush_aliases_dir = Path::join($drush_aliases_dir, 'sites');
    }
    $this->command->setDrushAliasesDir($drush_aliases_dir);

    $inputs = [$alias_version];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertFileDoesNotExist($drush_archive_filepath);
    $this->assertFileExists($drush_aliases_dir);
    $this->assertStringContainsString('Cloud Platform Drush aliases installed into ' . $drush_aliases_dir, $output);

  }

}
