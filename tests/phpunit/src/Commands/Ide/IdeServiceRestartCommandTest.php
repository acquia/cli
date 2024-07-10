<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeServiceRestartCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property IdeServiceRestartCommandTest $command
 */
class IdeServiceRestartCommandTest extends CommandTestBase
{
    use IdeRequiredTestTrait;

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(IdeServiceRestartCommand::class);
    }

    public function testIdeServiceRestartCommand(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockRestartPhp($localMachineHelper);

        $this->executeCommand(['service' => 'php'], []);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Restarted php', $output);
    }

    /**
     * @group brokenProphecy
     */
    public function testIdeServiceRestartCommandInvalid(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockRestartPhp($localMachineHelper);

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Specify a valid service name');
        $this->executeCommand(['service' => 'rambulator'], []);
    }
}
