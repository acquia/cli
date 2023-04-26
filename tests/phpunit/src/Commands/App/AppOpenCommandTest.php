<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppOpenCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\App\AppOpenCommand $command
 */
class AppOpenCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(AppOpenCommand::class);
  }

  /**
   * Tests the 'app:open' command.
   */
  public function testAppOpenCommand(): void {
    $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->startBrowser('https://cloud.acquia.com/a/applications/' . $application_uuid)->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->createMockAcliConfigFile($application_uuid);
    $this->mockApplicationRequest();
    $this->executeCommand([], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
