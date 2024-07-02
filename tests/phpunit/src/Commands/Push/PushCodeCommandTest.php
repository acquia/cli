<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Push\PushCodeCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Push\PushCodeCommand $command
 */
class PushCodeCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(PushCodeCommand::class);
    }

    public function testPushCode(): void
    {
        $this->executeCommand();

        $output = $this->getDisplay();

        $this->assertStringContainsString('Use git to push code changes upstream.', $output);
    }
}
