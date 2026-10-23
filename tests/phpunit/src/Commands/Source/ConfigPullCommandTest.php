<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPullCommand;
use Acquia\Cli\Command\Source\SourceCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use org\bovigo\vfs\vfsStream;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property ConfigPullCommand $command
 */
class ConfigPullCommandTest extends CommandTestBase
{
    /**
     * The site UUID in system.site is what an import checks first; it is not
     * the site ID the commands address.
     */
    private const DOCUMENT = "'':\n  system.site:\n    name: Site\n    uuid: 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70\nlanguage.nl:\n  system.site:\n    name: Website\n";

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
        $this->assertStringEqualsFile($this->configDir . '/system.site.yml', "name: Site\nuuid: 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70\n");
        $this->assertStringEqualsFile($this->configDir . '/language/nl/system.site.yml', "name: Website\n");
        $this->assertFileDoesNotExist($this->configDir . '/stale.yml');
        $this->assertFileDoesNotExist($this->configDir . '/leftover.yml');
        $this->assertDirectoryDoesNotExist($this->configDir . '.tmp');
        $this->assertStringContainsString("Exporting Source site site-a's configuration...", $this->getDisplay());
        $this->assertStringContainsString('Exported 2 configuration files from Source site site-a to', $this->getDisplay());
    }

    public function testUsesRecordedSite(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-r\n");
        $this->mockConfig('site-r');
        $this->executeCommand();
        $this->assertFileExists($this->configDir . '/system.site.yml');
    }

    public function testFindsLinkedRootFromSubdirectoryBeforeFirstPull(): void
    {
        // Linked at the root, nothing pulled yet, run from a subdirectory.
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-r\n");
        $this->fs->mkdir($this->projectDir . '/sub');
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($this->command, $this->projectDir . '/sub');
        $this->mockConfig('site-r');
        $this->executeCommand();
        $this->assertFileExists($this->configDir . '/system.site.yml');
        $this->assertDirectoryDoesNotExist($this->projectDir . '/sub/.acquia');
    }

    public function testIgnoresAppLinkConfigAboveWhenNothingPulledYet(): void
    {
        // The fixture's <root>/.acquia-cli.yml is app:link's (no source_site_id):
        // it must not make <root> the working copy of a pull run from a
        // subdirectory, which is then its own, unlinked working copy.
        $this->fs->mkdir($this->projectDir . '/sub');
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($this->command, $this->projectDir . '/sub');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not determine the Source site.');
        $this->executeCommand();
    }

    public function testKeepsLookingAboveAppLinkConfig(): void
    {
        // Linked one level above the fixture's app:link file: the lookup must
        // pass that file by, not stop at it.
        $above = dirname($this->projectDir);
        file_put_contents($above . '/.acquia-cli.yml', "source_site_id: site-r\n");
        $this->fs->mkdir($this->projectDir . '/sub');
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($this->command, $this->projectDir . '/sub');
        $this->mockConfig('site-r');
        $this->executeCommand();
        $this->assertFileExists($above . '/.acquia/config/system.site.yml');
        $this->assertDirectoryDoesNotExist($this->projectDir . '/.acquia');
    }

    public function testInvalidSiteOptionThrowsBeforeRequesting(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('"a/../b" is not a valid Source site ID: only letters, digits and hyphens are allowed.');
        $this->executeCommand(['--site' => 'a/../b']);
    }

    public function testTrailingNewlineInSiteOptionThrowsBeforeRequesting(): void
    {
        // Without the D modifier, $ also matches before a final newline.
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage("\"site-a\n\" is not a valid Source site ID: only letters, digits and hyphens are allowed.");
        $this->executeCommand(['--site' => "site-a\n"]);
    }

    public function testInvalidRecordedSiteThrowsBeforeRequesting(): void
    {
        // .acquia-cli.yml is hand-editable.
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: 'a/../b'\n");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('"a/../b" is not a valid Source site ID: only letters, digits and hyphens are allowed.');
        $this->executeCommand();
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
        $this->expectExceptionMessage('Could not determine the Source site. Pass --site=<sourceSiteId> or run acli source:cms:link first.');
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

    public function testUnsafeNameWritesNothing(): void
    {
        $this->fs->dumpFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->mockConfig('site-a', "'':\n  system.site:\n    name: Site\n  ../evil:\n    name: Evil\n");
        try {
            $this->executeCommand(['--site' => 'site-a']);
            $this->fail('Expected an exception');
        } catch (AcquiaCliException $e) {
            $this->assertSame('"../evil" is not a valid configuration name.', $e->getMessage());
        }
        $this->assertStringEqualsFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->assertDirectoryDoesNotExist($this->configDir . '.tmp');
        $this->assertFileDoesNotExist($this->projectDir . '/.acquia/evil.yml');
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

    public function testSwapFailureRestoresExistingConfig(): void
    {
        $this->fs->dumpFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->mockConfig('site-a');
        // The swap is two renames: .acquia/config → .old, then .tmp → config.
        // Fail the second one, after the old directory has been moved aside.
        // (dumpFile() renames too, per file, so key on the swap's target; and
        // the rollback renames to the same target, so fail only once.)
        $configDir = $this->configDir;
        $filesystem = new class ($configDir) extends Filesystem {
            private bool $failed = false;

            public function __construct(private readonly string $configDir)
            {
            }

            public function rename(string $origin, string $target, bool $overwrite = false): void
            {
                if ($target === $this->configDir && !$this->failed) {
                    $this->failed = true;
                    throw new IOException('Simulated rename failure');
                }
                parent::rename($origin, $target, $overwrite);
            }
        };
        $this->command->localMachineHelper = new class ($this->input, $this->output, $this->logger, $filesystem) extends LocalMachineHelper {
            public function __construct(ArrayInput $input, OutputInterface $output, ConsoleLogger $logger, private readonly Filesystem $filesystem)
            {
                parent::__construct($input, $output, $logger);
            }

            public function getFilesystem(): Filesystem
            {
                return $this->filesystem;
            }
        };
        try {
            $this->executeCommand(['--site' => 'site-a']);
            $this->fail('Expected an exception');
        } catch (IOException $e) {
            $this->assertSame('Simulated rename failure', $e->getMessage());
        }
        $this->assertStringEqualsFile($this->configDir . '/system.site.yml', "name: Old\n");
        $this->assertDirectoryDoesNotExist($this->configDir . '.tmp');
        $this->assertDirectoryDoesNotExist($this->configDir . '.old');
    }
}
