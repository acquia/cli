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

    /**
     * Kill FalseValue mutation ($command['hidden'] ?? false -> ?? true).
     * Commands without a 'hidden' key must be treated as visible (not hidden).
     */
    public function testIsCommandHiddenInDocsTreatsMissingHiddenAsVisible(): void
    {
        $command = ['name' => 'list', 'usage' => ['list']];
        $this->assertFalse(
            MakeDocsCommand::isCommandHiddenInDocs($command),
            'Missing "hidden" key must be treated as visible (false); mutation ?? true would exclude these'
        );
    }

    /**
     * Commands with 'hidden' => true must be excluded from docs.
     */
    public function testIsCommandHiddenInDocsReturnsTrueWhenHidden(): void
    {
        $this->assertTrue(MakeDocsCommand::isCommandHiddenInDocs(['name' => 'x', 'hidden' => true]));
    }

    /**
     * Kill Coalesce mutation (false ?? $command['hidden']): hidden commands must be excluded.
     * When 'hidden' is true the command must be skipped (not in index, no file).
     */
    public function testMakeDocsCommandDumpExcludesHiddenCommands(): void
    {
        $vfs = vfsStream::setup('root');
        $this->executeCommand(['--dump' => $vfs->url()]);
        $index = json_decode($vfs->getChild('index.json')->getContent(), true);
        $commandNames = array_column($index, 'command');
        $this->assertNotContains('self:make-docs', $commandNames, 'Hidden commands must be excluded from index');
        $this->assertFalse($vfs->hasChild('self:make-docs.json'), 'Hidden commands must not have a doc file');
    }
}
