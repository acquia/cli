<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class CommandBaseTest.
 * @property LinkCommand $command
 */
class CommandBaseTest extends CommandTestBase {

  /**
   * @return \Acquia\Cli\Command\App\LinkCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(LinkCommand::class);
  }

  public function testUnauthenticatedFailure(): void {
    $this->clientServiceProphecy->isMachineAuthenticated(Argument::type(JsonFileStore::class))->willReturn(FALSE);
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
    $this->command = $this->injectCommand(IdeListCommand::class);
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
    ];
    $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');
    $this->executeCommand([], []);
    $this->prophet->checkPredictions();
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

  public function providerTestInvalidCloudEnvironmentAlias(): array {
    return [
      ['bl.a', 'This value is too short. It should have 5 characters or more.'],
      ['blarg', 'You must enter either an environment ID or alias. Environment aliases must match the pattern [app-name].[env]'],
      ['12345', 'You must enter either an environment ID or alias. Environment aliases must match the pattern [app-name].[env]'],
    ];
  }

  /**
   * @dataProvider providerTestInvalidCloudEnvironmentAlias
   *
   * @param string $alias
   * @param string $message
   *
   * @throws \Exception
   */
  public function testInvalidCloudEnvironmentAlias($alias, $message): void {
    try {
      CommandBase::validateEnvironmentAlias($alias);
    }
    catch (ValidatorException $e) {
      $this->assertEquals($message, $e->getMessage());
    }
  }

}
