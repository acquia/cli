<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\LinkCommand;
use Acquia\Cli\Command\Source\SourceCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionProperty;

/**
 * @property LinkCommand $command
 */
class LinkCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $command = $this->injectCommand(LinkCommand::class);
        $this->setCwd($command, $this->projectDir);
        return $command;
    }

    private function setCwd(CommandBase $command, string $cwd): void
    {
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($command, $cwd);
    }

    private function mockSite(string $id): void
    {
        $this->clientProphecy->request('get', "/source-sites/$id")
            ->willReturn((object) ['id' => $id, 'label' => "Site $id"])
            ->shouldBeCalled();
    }

    public function testArgumentRecordsSite(): void
    {
        $this->mockSite('site-a');
        $this->executeCommand(['sourceSiteId' => 'site-a']);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->assertStringContainsString('Linked this working copy to Source site Site site-a (site-a) by writing to', $this->getDisplay());
        $this->assertStringContainsString($this->projectDir . '/.acquia-cli.yml', $this->getDisplay());
    }

    public function testArgumentOverwritesExistingLink(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->mockSite('site-b');
        $this->executeCommand(['sourceSiteId' => 'site-b']);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-b\n");
    }

    public function testInvalidArgumentThrowsBeforeRequesting(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('"../applications" is not a valid Source site ID: only letters, digits and hyphens are allowed.');
        $this->executeCommand(['sourceSiteId' => '../applications']);
    }

    public function testAlreadyLinkedWithoutArgument(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->executeCommand();
        $this->assertSame(1, $this->getStatusCode());
        $this->assertStringContainsString('already linked to Source site site-a. Run acli source:link <sourceSiteId> to link it to another site.', $this->getDisplay());
    }

    public function testNonInteractiveWithoutArgumentThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Pass the Source site ID as the sourceSiteId argument when running non-interactively.');
        $this->executeCommand([], [], interactive: false);
    }

    public function testChooserRecordsChosenSite(): void
    {
        $this->clientProphecy->request('get', '/source-sites')
            ->willReturn([(object) ['id' => 'site-a', 'label' => 'Site A'], (object) ['id' => 'site-b', 'label' => 'Site B']])
            ->shouldBeCalled();
        $this->executeCommand([], [1]);
        $this->assertStringContainsString('Select a Source site', $this->getDisplay());
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-b\n");
    }

    public function testNoSitesThrows(): void
    {
        $this->clientProphecy->request('get', '/source-sites')->willReturn([])->shouldBeCalled();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('There are no Source sites to link to.');
        $this->executeCommand();
    }

    public function testWritesAtWorkingCopyRootFromSubdirectory(): void
    {
        mkdir($this->projectDir . '/.acquia/config/language/nl', 0777, true);
        $this->setCwd($this->command, $this->projectDir . '/.acquia/config/language/nl');
        $this->mockSite('site-a');
        $this->executeCommand(['sourceSiteId' => 'site-a']);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
    }

    public function testWritesInCwdWhenNothingPulledYet(): void
    {
        mkdir($this->projectDir . '/fresh');
        $this->setCwd($this->command, $this->projectDir . '/fresh');
        $this->mockSite('site-a');
        $this->executeCommand(['sourceSiteId' => 'site-a']);
        $this->assertStringEqualsFile($this->projectDir . '/fresh/.acquia-cli.yml', "source_site_id: site-a\n");
    }
}
