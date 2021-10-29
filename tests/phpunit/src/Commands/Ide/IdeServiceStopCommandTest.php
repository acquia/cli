<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeServiceStopCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeServiceStopCommandTest.
 *
 * @property IdeServiceStopCommandTest $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeServiceStopCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeServiceStopCommand::class);
  }

  /**
   * Tests the 'ide:service-stop' command.
   *
   * @throws \Exception
   */
  public function testIdeServiceStopCommand(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Stopping php', $output);
  }

  /**
   * Tests the 'ide:service-stop' command with invalid choice.
   *
   * @throws \Exception
   */
  public function testIdeServiceStopCommandInvalid(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    try {
      $this->executeCommand(['service' => 'rambulator'], []);
    }
    catch (Exception $exception) {
      $this->assertStringContainsString('Please specify a valid service name', $exception->getMessage());
    }
  }

}
