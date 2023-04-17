<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeServiceRestartCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property IdeServiceRestartCommandTest $command
 */
class IdeServiceRestartCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  protected function createCommand(): Command {
    return $this->injectCommand(IdeServiceRestartCommand::class);
  }

  /**
   * Tests the 'ide:service-restart' command.
   */
  public function testIdeServiceRestartCommand(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Restarted php', $output);
  }

  /**
   * Tests the 'ide:service-restart' command with invalid choice.
   */
  public function testIdeServiceRestartCommandInvalid(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
