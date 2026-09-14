<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPullCommand;
use Acquia\Cli\Command\Source\SourceCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use org\bovigo\vfs\vfsStream;
use ReflectionProperty;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @property ConfigPullCommand $command
 */
class ConfigPullCommandTest extends CommandTestBase
{
    private const DOCUMENT = "'':\n  system.site:\n    name: Site\nlanguage.nl:\n  system.site:\n    name: Website\n";

    private string $configDir;

    protected function createCommand(): CommandBase
    {
        $command = $this->injectCommand(ConfigPullCommand::class);
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($command, $this->projectDir);
        $this->configDir = $this->projectDir . '/.acquia/config';
        return $command;
    }

    private function mockConfig(string $siteId, string $document = self::DOCUMENT): void
    {
        $this->clientProphecy->request('get', "/source-sites/$siteId/config")
            ->willReturn((object) ['configuration' => $document])
            ->shouldBeCalled();
    }

    public function testWritesFilesAndRemovesStaleOnes(): void
    {
        $this->fs->dumpFile($this->configDir . '/stale.yml', "old: true\n");
        $this->fs->dumpFile($this->configDir . '.tmp/leftover.yml', "old: true\n");
        $this->mockConfig('site-a');
        $this->executeCommand(['--site' => 'site-a']);
        $this->assertStringEqualsFile($this->configDir . '/system.site.yml', "name: Site\n");
        $this->assertStringEqualsFile($this->configDir . '/language/nl/system.site.yml', "name: Website\n");
        $this->assertFileDoesNotExist($this->configDir . '/stale.yml');
        $this->assertFileDoesNotExist($this->configDir . '/leftover.yml');
        $this->assertDirectoryDoesNotExist($this->configDir . '.tmp');
        $this->assertStringContainsString('Exported 2 configuration files from Source site site-a to', $this->getDisplay());
    }

    public function testUsesRecordedSite(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-r\n");
        $this->mockConfig('site-r');
        $this->executeCommand();
        $this->assertFileExists($this->configDir . '/system.site.yml');
    }

    public function testSiteOptionWinsOverRecordedSite(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-r\n");
        $this->mockConfig('site-a');
        $this->executeCommand(['--site' => 'site-a']);
        $this->assertFileExists($this->configDir . '/system.site.yml');
    }

    public function testJsonOutput(): void
    {
        $this->mockConfig('site-a');
        $this->executeCommand(['--site' => 'site-a', '--format' => 'json']);
        $this->assertSame(json_encode(['directory' => $this->configDir, 'files' => ['system.site.yml', 'language/nl/system.site.yml']], JSON_PRETTY_PRINT) . "\n", $this->getDisplay());
    }

    public function testUnknownFormatThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Unknown output format "xml". Use txt or json.');
        $this->executeCommand(['--site' => 'site-a', '--format' => 'xml']);
    }

    public function testNoSiteThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not determine the Source site. Pass --site=<sourceSiteId> or run acli source:link first.');
        $this->executeCommand();
    }

    public function testEmptyDocumentLeavesExistingConfigIntact(): void
    {
        $this->fs->dumpFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->mockConfig('site-a', '{}');
        try {
            $this->executeCommand(['--site' => 'site-a']);
            $this->fail('Expected an exception');
        } catch (AcquiaCliException $e) {
            $this->assertSame('The configuration document is empty or not a map of collections.', $e->getMessage());
        }
        $this->assertStringEqualsFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->assertDirectoryDoesNotExist($this->configDir . '.tmp');
    }

    public function testWriteFailureLeavesExistingConfigIntact(): void
    {
        $this->fs->dumpFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->mockConfig('site-a');
        // Room for the first file only.
        vfsStream::setQuota(strlen("name: Old\n") + strlen("name: Site\n"));
        try {
            $this->executeCommand(['--site' => 'site-a']);
            $this->fail('Expected an exception');
        } catch (IOException) {
        }
        vfsStream::setQuota(-1);
        $this->assertStringEqualsFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->assertDirectoryDoesNotExist($this->configDir . '.tmp');
    }
}
