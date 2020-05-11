<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\UnlinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class UnlinkCommandTest.
 *
 * @property \Acquia\Cli\Command\UnlinkCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class UnlinkCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new UnlinkCommand();
  }

  /**
   * Tests the 'unlink' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUnlinkCommand(): void {
    $this->setCommand($this->createCommand());
    $application_uuid = 'testuuid';
    $this->application->getDatastore()->set($this->application->getAcliConfigFilename(), [
      'localProjects' => [
        0 => [
          'directory' => $this->projectFixtureDir,
          'cloud_application_uuid' => $application_uuid,
        ],
      ],
    ]);

    // Assert we set it correctly.
    $acquia_cli_config = $this->application->getDatastore()->get($this->application->getAcliConfigFilename());
    $this->assertIsArray($acquia_cli_config);
    $this->assertArrayHasKey('localProjects', $acquia_cli_config);
    $this->assertArrayHasKey(0, $acquia_cli_config['localProjects']);
    $this->assertArrayHasKey('cloud_application_uuid', $acquia_cli_config['localProjects'][0]);
    $this->assertEquals($application_uuid, $acquia_cli_config['localProjects'][0]['cloud_application_uuid']);

    $this->executeCommand([], []);
    $output = $this->getDisplay();

    // Assert it's been unset.
    $acquia_cli_config = $this->application->getDatastore()->get($this->application->getAcliConfigFilename());
    $this->assertIsArray($acquia_cli_config);
    $this->assertArrayHasKey('localProjects', $acquia_cli_config);
    $this->assertArrayNotHasKey(0, $acquia_cli_config['localProjects']);
    $this->assertStringContainsString("Unlinked {$this->projectFixtureDir} from Cloud application $application_uuid", $output);
  }

}
