<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\Ide\IdeCreateCommand;
use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;
use Webmozart\PathUtil\Path;

/**
 * Class CommandBaseTest.
 * @property LinkCommand $command
 */
class CommandBaseTest extends CommandTestBase {

  /**
   * @return \Acquia\Cli\Command\LinkCommand
   */
  protected function createCommand(): Command {
    return new LinkCommand();
  }

  public function testUnauthenticatedFailure(): void {
    $this->removeMockConfigFiles();
    $this->setCommand($this->createCommand());
    try {
      $this->executeCommand([], []);
    }
    catch (\Exception $e) {
      $this->assertEquals('This machine is not yet authenticated with Acquia Cloud. Please run `acli auth:login`', $e->getMessage());
    }
  }

  public function testCloudAppUuidArg(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();
    $application_response = $this->mockApplicationRequest($cloud_client);
    $this->application->setAcquiaCloudClient($cloud_client->reveal());
    $this->executeCommand([
      '--cloud-app-uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
    ], []);
  }

  public function testInvalidCloudAppUuidArg(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();
    $this->application->setAcquiaCloudClient($cloud_client->reveal());
    try {
      $this->executeCommand([
        '--cloud-app-uuid' => 'a47ac10b-i-do-not-feel-validated',
      ], []);
    }
    catch (ValidatorException $e) {
      $this->assertEquals('This is not a valid UUID.', $e->getMessage());
    }
  }

}
