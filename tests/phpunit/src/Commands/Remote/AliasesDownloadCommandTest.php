<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\AliasesDownloadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
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
    return new AliasesDownloadCommand();
  }

  /**
   * Tests the 'remote:aliases:download' commands.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRemoteAliasesDownloadCommand(): void {
    $this->setCommand($this->createCommand());

    $drush_aliases_fixture = __DIR__ . '/../../../../fixtures/drush-aliases';
    $drush_aliases_tarball_fixture_filepath = tempnam(sys_get_temp_dir(), 'AcquiaDrushAliases');
    $archive_fixture = new \PharData($drush_aliases_tarball_fixture_filepath . '.tar');
    $archive_fixture->buildFromDirectory($drush_aliases_fixture);
    $archive_fixture->compress(\Phar::GZ);

    $stream = stream_for(file_get_contents($drush_aliases_tarball_fixture_filepath . '.tar.gz'));
    $cloud_client = $this->getMockClient();
    $cloud_client->request('get', '/account/drush-aliases/download')->willReturn($stream);
    $this->application->setAcquiaCloudClient($cloud_client->reveal());
    $drush_archive_filepath = $this->command->getDrushArchiveTempFilepath();
    $drush_aliases_dir = sys_get_temp_dir() . './drush';
    $this->command->setDrushAliasesDir($drush_aliases_dir);

    $inputs = [];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertFileDoesNotExist($drush_archive_filepath);
    $this->assertFileExists($drush_aliases_dir);
    $this->assertStringContainsString("Acquia Cloud Drush Aliases archive downloaded to $drush_archive_filepath", $output);
    $this->assertStringContainsString('Acquia Cloud Drush aliases installed into ' . $drush_aliases_dir, $output);

  }

}
