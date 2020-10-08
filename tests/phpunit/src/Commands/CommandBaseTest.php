<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Class CommandBaseTest.
 * @property LinkCommand $command
 */
class CommandBaseTest extends CommandTestBase {

  /**
   * @return \Acquia\Cli\Command\LinkCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(LinkCommand::class);
  }

  public function testUnauthenticatedFailure(): void {
    $this->removeMockConfigFiles();

    $inputs = [
      // Would you like to share anonymous performance usage and data?
      'n',
    ];
    try {
      $this->executeCommand([], $inputs);
    }
    catch (Exception $e) {
      $this->assertEquals('This machine is not yet authenticated with the Cloud Platform. Please run `acli auth:login`', $e->getMessage());
    }
  }

  public function testCloudAppFromLocalConfig(): void {
    $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');

    $this->mockApplicationRequest();
    $this->executeCommand([], []);
  }

  public function providerTestCloudAppUuidArg(): array {
    return [
      ['a47ac10b-58cc-4372-a567-0e02b2c3d470'],
      ['165c887b-7633-4f64-799d-a5d4669c768e'],
    ];
  }

  /**
   * @dataProvider providerTestCloudAppUuidArg
   *
   * @param string $uuid
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testCloudAppUuidArg($uuid): void {
    $this->mockApplicationRequest();
    $this->assertEquals($uuid, CommandBase::validateUuid($uuid));
  }

  public function providerTestInvalidCloudAppUuidArg(): array {
    return [
      ['a47ac10b-58cc-4372-a567-0e02b2c3d4', 'This value should have exactly 36 characters.'],
      ['a47ac10b-58cc-4372-a567-0e02b2c3d47z', 'This is not a valid UUID.'],
    ];
  }

  /**
   * @dataProvider providerTestInvalidCloudAppUuidArg
   *
   * @param string $uuid
   * @param string $message
   *
   * @throws \Exception
   */
  public function testInvalidCloudAppUuidArg($uuid, $message): void {
    try {
      CommandBase::validateUuid($uuid);
    }
    catch (ValidatorException $e) {
      $this->assertEquals($message, $e->getMessage());
    }
  }

}
