<?php

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class ApiListCommandTest extends CommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $api_command_helper = new ApiCommandHelper(
      $this->cloudConfigFilepath,
      $this->localMachineHelper,
      $this->cloudDatastore,
      $this->acliDatastore,
      $this->telemetryHelper,
      $this->amplitudeProphecy->reveal(),
      $this->acliConfigFilename,
      $this->projectFixtureDir,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper
    );
    $this->application->addCommands($api_command_helper->getApiCommands());
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ApiListCommand::class);
  }

  /**
   * Tests the 'api:list' command.
   *
   * @throws \Exception
   */
  public function testApiListCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString(' api:accounts:ssh-keys-list', $output);
  }

}
