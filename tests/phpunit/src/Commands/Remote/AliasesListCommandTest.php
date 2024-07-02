<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Remote\AliasListCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property AliasListCommand $command
 */
class AliasesListCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AliasListCommand::class);
    }

    public function testRemoteAliasesListCommand(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $this->mockApplicationRequest();
        $this->mockEnvironmentsRequest($applicationsResponse);

        $inputs = [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
        'n',
        // Select a Cloud Platform application:
        '0',
        // Would you like to link the project at ...
        'n',
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();

        $this->assertStringContainsString('| Sample application 1 | devcloud2.dev     | 24-a47ac10b-58cc-4372-a567-0e02b2c3d470 |', $output);
    }
}
