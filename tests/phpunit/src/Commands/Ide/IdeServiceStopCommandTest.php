<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeServiceStopCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property IdeServiceStopCommandTest $command
 */
class IdeServiceStopCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  protected function createCommand(): Command {
    return $this->injectCommand(IdeServiceStopCommand::class);
  }

  /**
   * Tests the 'ide:service-stop' command.
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
   */
  public function testIdeServiceStopCommandInvalid(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
