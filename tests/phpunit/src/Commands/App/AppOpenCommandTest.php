<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppOpenCommand;
use Acquia\Cli\Exception\AcquiaCliException;
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
    $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->startBrowser('https://cloud.acquia.com/a/applications/' . $applicationUuid)->shouldBeCalled();
    $localMachineHelper->isBrowserAvailable()->willReturn(TRUE);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->createMockAcliConfigFile($applicationUuid);
    $this->mockApplicationRequest();
    $this->executeCommand();
    $this->prophet->checkPredictions();
  }

  public function testAppOpenNoBrowser(): void {
    $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->isBrowserAvailable()->willReturn(FALSE);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->mockApplicationRequest();
    $this->createMockAcliConfigFile($applicationUuid);
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('No browser is available on this machine');
    $this->executeCommand();
  }

}
