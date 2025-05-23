<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\MakeDocsCommand;
use Acquia\Cli\Tests\CommandTestBase;
use org\bovigo\vfs\vfsStream;

/**
 * @property \Acquia\Cli\Command\Self\MakeDocsCommand $command
 */
class MakeDocsCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(MakeDocsCommand::class);
    }

    public function testMakeDocsCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Console Tool', $output);
        $this->assertStringContainsString('============', $output);
        $this->assertStringContainsString('- `help`_', $output);
    }

    public function testMakeDocsCommandDump(): void
    {
        $vfs = vfsStream::setup('root');
        $this->executeCommand(['--dump' => $vfs->url()]);
        $this->assertStringContainsString('The completion command dumps', $vfs->getChild('completion.json')->getContent());
    }
}
