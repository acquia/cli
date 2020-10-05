<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeServiceRestartCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeServiceRestartCommandTest.
 *
 * @property IdeServiceRestartCommandTest $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeServiceRestartCommandTest extends IdeRequiredTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeServiceRestartCommand::class);
  }

  /**
   * Tests the 'ide:service-restart' command.
   *
   * @throws \Exception
   */
  public function testIdeServiceRestartCommand(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand(['service' => 'php'], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Restarted php-fpm', $output);
  }

  /**
   * Tests the 'ide:service-restart' command with invalid choice.
   *
   * @throws \Exception
   */
  public function testIdeServiceRestartCommandInvalid(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    try {
      $this->executeCommand(['service' => 'rambulator'], []);
    }
    catch (\Exception $exception) {
      $this->assertStringContainsString('Please specify a valid service name', $exception->getMessage());
    }
  }

}
