<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\SelfInfoCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Self\SelfInfoCommand $command
 */
class SelfInfoCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SelfInfoCommand::class);
    }

    /**
     * @throws \Exception
     */
    public function testSelfInfoCommand(): void
    {
        $this->mockRequest('getAccount');
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Property', $output);
        $this->assertStringContainsString('--------', $output);
        $this->assertStringContainsString('Version', $output);
        $this->assertStringContainsString('Cloud datastore', $output);
        $this->assertStringContainsString('ACLI datastore', $output);
        $this->assertStringContainsString('Telemetry enabled', $output);
        $this->assertStringContainsString('User ID', $output);
        $this->assertStringContainsString('is_acquian', $output);
    }
}
