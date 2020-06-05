<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\UnlinkCommand;
use Acquia\Cli\Exception\AcquiaCliException;
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
    return $this->injectCommand(UnlinkCommand::class);
  }

  /**
   * Tests the 'unlink' command.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUnlinkCommand(): void {

    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $cloud_application = $applications_response->{'_embedded'}->items[0];
    $cloud_application_uuid = $cloud_application->uuid;
    $this->createMockAcliConfigFile($cloud_application_uuid);
    $this->mockApplicationRequest();

    // Assert we set it correctly.
    $acquia_cli_config = $this->acliDatastore->get($this->acliConfigFilename);
    $this->assertIsArray($acquia_cli_config);
    $this->assertArrayHasKey('localProjects', $acquia_cli_config);
    $this->assertArrayHasKey(0, $acquia_cli_config['localProjects']);
    $this->assertArrayHasKey('cloud_application_uuid', $acquia_cli_config['localProjects'][0]);
    $this->assertEquals($cloud_application_uuid, $acquia_cli_config['localProjects'][0]['cloud_application_uuid']);

    $this->executeCommand([], []);
    $output = $this->getDisplay();

    // Assert it's been unset.
    $acquia_cli_config = $this->acliDatastore->get($this->acliConfigFilename);
    $this->assertIsArray($acquia_cli_config);
    $this->assertArrayHasKey('localProjects', $acquia_cli_config);
    $this->assertArrayNotHasKey(0, $acquia_cli_config['localProjects']);
    $this->assertStringContainsString("Unlinked {$this->projectFixtureDir} from Cloud application " . $cloud_application->name, $output);
  }

  public function testUnlinkCommandInvalidDir(): void {

    try {
      $this->executeCommand([], []);
    }
    catch (\Exception $exception) {
      $this->assertStringContainsString('There is no Acquia Cloud application linked to', $exception->getMessage());
    }
  }

}
