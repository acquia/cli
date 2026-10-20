<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\SourceCommandBase;
use Acquia\Cli\Command\Source\UnlinkCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionProperty;

/**
 * @property UnlinkCommand $command
 */
class UnlinkCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $command = $this->injectCommand(UnlinkCommand::class);
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($command, $this->projectDir);
        return $command;
    }

    public function testUnlinksSite(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->executeCommand();
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Unlinked this working copy from Source site site-a.', $this->getDisplay());
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', '{  }');
    }

    public function testPreservesOtherKeys(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\ncloud_app_uuid: kept\n");
        $this->executeCommand();
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "cloud_app_uuid: kept\n");
    }

    public function testNotLinkedThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('This working copy is not linked to a Source site.');
        $this->executeCommand();
    }

    public function testWritesAtWorkingCopyRootFromSubdirectory(): void
    {
        mkdir($this->projectDir . '/.acquia/config/language/nl', 0777, true);
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($this->command, $this->projectDir . '/.acquia/config/language/nl');
        $this->executeCommand();
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', '{  }');
    }
}
