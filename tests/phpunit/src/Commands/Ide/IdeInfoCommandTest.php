<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeInfoCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Ide\IdeListCommand $command
 */
class IdeInfoCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(IdeInfoCommand::class);
    }

    /**
     * @group brokenProphecy
     */
    public function testIdeInfoCommand(): void
    {
        $applications = $this->mockRequest('getApplications');
        $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
        $ides = $this->mockRequest('getApplicationIdes', $applications[0]->uuid);
        $this->mockRequest('getIde', $ides[0]->uuid);
        $inputs = [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
        // Select the application.
            0,
        // Would you like to link the project at ... ?
            'y',
        // Select an IDE ...
            0,
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('[1] Sample application 2', $output);
        $this->assertStringContainsString('IDE property        IDE value', $output);
        $this->assertStringContainsString('UUID                215824ff-272a-4a8c-9027-df32ed1d68a9', $output);
        $this->assertStringContainsString('Label               Example IDE', $output);
    }
}
