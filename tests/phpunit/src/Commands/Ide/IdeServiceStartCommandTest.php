<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeServiceStartCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property IdeServiceStartCommandTest $command
 */
class IdeServiceStartCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  protected function createCommand(): Command {
    return $this->injectCommand(IdeServiceStartCommand::class);
  }

  public function testIdeServiceStartCommand(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockStartPhp($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Starting php', $output);
  }

  public function testIdeServiceStartCommandInvalid(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockStartPhp($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
