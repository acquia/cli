<?php

declare(strict_types = 1);

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

  public function testIdeServiceRestartCommand(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Restarted php', $output);
  }

  public function testIdeServiceRestartCommandInvalid(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
