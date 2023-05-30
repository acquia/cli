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

  public function testIdeServiceStopCommand(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Stopping php', $output);
  }

  public function testIdeServiceStopCommandInvalid(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
