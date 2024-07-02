<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeOpenCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property IdeOpenCommand $command
 */
class IdeOpenCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(IdeOpenCommand::class);
    }

    /**
     * @group brokenProphecy
     */
    public function testIdeOpenCommand(): void
    {
        $applications = $this->mockRequest('getApplications');
        $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
        $this->mockRequest('getApplicationIdes', $applications[0]->uuid);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->isBrowserAvailable()->willReturn(true);
        $localMachineHelper->startBrowser('https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.ides.acquia.com')->willReturn(true);

        $inputs = [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
        'n',
        // Select a Cloud Platform application:
        0,
        // Would you like to link the project at ... ?
        'y',
        // Select the IDE you'd like to open:
        0,
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Select the IDE you\'d like to open:', $output);
        $this->assertStringContainsString('[0] IDE Label 1', $output);
        $this->assertStringContainsString('Your IDE URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.ides.acquia.com', $output);
        $this->assertStringContainsString('Your Drupal Site URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.web.ahdev.cloud', $output);
        $this->assertStringContainsString('Opening your IDE in browser...', $output);
    }
}
