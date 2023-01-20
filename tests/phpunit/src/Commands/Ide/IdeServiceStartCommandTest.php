<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeServiceStartCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Class IdeServiceStartCommandTest.
 *
 * @property IdeServiceStartCommandTest $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeServiceStartCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeServiceStartCommand::class);
  }

  /**
   * Tests the 'ide:service-start' command.
   *
   * @throws \Exception
   */
  public function testIdeServiceStartCommand(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockStartPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Starting php', $output);
  }

  /**
   * Tests the 'ide:service-start' command with invalid choice.
   *
   * @throws \Exception
   */
  public function testIdeServiceStartCommandInvalid(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockStartPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->expectException(ValidatorException::class);
    $this->expectExceptionMessage('Please specify a valid service name');
    $this->executeCommand(['service' => 'rambulator'], []);
  }

}
