<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeServiceStopCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property IdeServiceStopCommandTest $command
 */
class IdeServiceStopCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  protected function createCommand(): CommandBase {
    return $this->injectCommand(IdeServiceStopCommand::class);
  }

  public function testIdeServiceStopCommand(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($localMachineHelper);

    $this->executeCommand(['service' => 'php'], []);

    // Assert.

    $output = $this->getDisplay();
    $this->assertStringContainsString('Stopping php', $output);
  }

  /**
   * @group brokenProphecy
   */
  public function testIdeServiceStopCommandInvalid(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockStopPhp($localMachineHelper);

    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
