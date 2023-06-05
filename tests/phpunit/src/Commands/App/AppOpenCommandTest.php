<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppOpenCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property AppOpenCommand $command
 */
class AppOpenCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(AppOpenCommand::class);
  }

  public function testAppOpenCommand(): void {
    putenv('DISPLAY=1');
    $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->startBrowser('https://cloud.acquia.com/a/applications/' . $applicationUuid)->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->createMockAcliConfigFile($applicationUuid);
    $this->mockApplicationRequest();
    $this->executeCommand();

    // Assert.
    $this->prophet->checkPredictions();
    $this->getDisplay();
    putenv('DISPLAY');
  }

}
