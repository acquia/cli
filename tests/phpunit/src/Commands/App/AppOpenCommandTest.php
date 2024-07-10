<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppOpenCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property AppOpenCommand $command
 */
class AppOpenCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AppOpenCommand::class);
    }

    public function testAppOpenCommand(): void
    {
        $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->startBrowser('https://cloud.acquia.com/a/applications/' . $applicationUuid)->shouldBeCalled();
        $localMachineHelper->isBrowserAvailable()->willReturn(true);
        $this->mockRequest('getApplicationByUuid', $applicationUuid);
        $this->executeCommand(['applicationUuid' => $applicationUuid]);
    }

    /**
     * @group brokenProphecy
     */
    public function testAppOpenNoBrowser(): void
    {
        $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->isBrowserAvailable()->willReturn(false);

        $this->mockApplicationRequest();
        $this->createMockAcliConfigFile($applicationUuid);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No browser is available on this machine');
        $this->executeCommand();
    }
}
