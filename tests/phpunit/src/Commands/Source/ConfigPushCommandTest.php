<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPushCommand;
use Acquia\Cli\Command\Source\SourceCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use ReflectionProperty;

/**
 * @property ConfigPushCommand $command
 */
class ConfigPushCommandTest extends CommandTestBase
{
    /**
     * The site UUID in system.site is what an import checks first; it is not
     * the site ID the commands address.
     */
    private const DOCUMENT = "'':\n  system.site:\n    name: Site\n    uuid: 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70\nlanguage.nl:\n  system.site:\n    name: Website\n";

    private string $configDir;

    protected function createCommand(): CommandBase
    {
        $command = $this->injectCommand(ConfigPushCommand::class);
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($command, $this->projectDir);
        $this->configDir = $this->projectDir . '/.acquia/config';
        $this->fs->dumpFile($this->configDir . '/language/nl/system.site.yml', "name: Website\n");
        $this->fs->dumpFile($this->configDir . '/system.site.yml', "name: Site\nuuid: 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70\n");
        $this->fs->dumpFile($this->configDir . '/.htaccess', "Deny from all\n");
        return $command;
    }

    private function mockPut(): void
    {
        $this->clientProphecy->request('put', '/source-sites/site-a/config', ['json' => ['configuration' => self::DOCUMENT]])
            ->willReturn((object) ['message' => 'accepted'])
            ->shouldBeCalled();
    }

    /**
     * @param array<object> $imports
     */
    private function mockImport(array $imports): void
    {
        $this->clientProphecy->request('get', '/source-sites/site-a/config/import')
            ->willReturn(...$imports)
            ->shouldBeCalled();
    }

    public function testSucceeds(): void
    {
        $this->mockPut();
        $this->mockImport([(object) ['status' => 'running'], (object) ['status' => 'succeeded']]);
        $this->executeCommand(['--site' => 'site-a', '--force' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Imported .acquia/config into Source site site-a.', $this->getDisplay());
    }

    public function testRefusedListsViolations(): void
    {
        $this->mockPut();
        $this->mockImport([(object) [
            'status' => 'refused',
            'violations' => [
                (object) ['code' => 'not_allowed', 'collection' => 'language.nl', 'config' => 'system.site', 'message' => 'Not in the allow list.'],
                (object) ['code' => 'missing', 'config' => 'system.site', 'message' => 'Required.'],
                (object) ['code' => 'site_uuid_mismatch', 'message' => 'The configuration was exported from site 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70, not from this site.'],
            ],
        ],
        ]);
        $this->executeCommand(['--site' => 'site-a'], ['y']);
        $this->assertSame(1, $this->getStatusCode());
        $display = $this->getDisplay();
        $this->assertStringContainsString('Replace the configuration of Source site site-a with the contents of', $display);
        $this->assertStringContainsString('Source site site-a refused the configuration; nothing was imported:', $display);
        $this->assertStringContainsString(" - language.nl: system.site [not_allowed]: Not in the allow list.\n - system.site [missing]: Required.\n - document [site_uuid_mismatch]: The configuration was exported from site 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70, not from this site.\n", $display);
        $this->assertStringNotContainsString('The import into Source site site-a failed', $display);
    }

    public function testJsonOutput(): void
    {
        $this->mockPut();
        $import = (object) ['status' => 'refused', 'violations' => [(object) ['code' => 'too_large', 'message' => 'Too large.']]];
        $this->mockImport([$import]);
        $this->executeCommand(['--site' => 'site-a', '--force' => true, '--format' => 'json']);
        $this->assertSame(1, $this->getStatusCode());
        $this->assertSame(json_encode($import, JSON_PRETTY_PRINT) . "\n", $this->getDisplay());
    }

    public function testFailed(): void
    {
        $this->mockPut();
        $this->mockImport([(object) ['status' => 'failed']]);
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
        $this->assertSame(1, $this->getStatusCode());
        // The error block wraps, so assert the two halves separately.
        $this->assertStringContainsString('The import into Source site site-a failed; the site was rolled back to', $this->getDisplay());
        $this->assertStringContainsString('previous configuration.', $this->getDisplay());
    }

    public function testUnknownStatusThrows(): void
    {
        $this->mockPut();
        $this->mockImport([(object) ['status' => 'weird']]);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('The import was accepted but its outcome is unknown (status weird). Check the site before pushing again. The import may still finish, and the status it reports may then be that of a later import.');
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
    }

    public function testConflictThrows(): void
    {
        $this->clientProphecy->request('put', '/source-sites/site-a/config', ['json' => ['configuration' => self::DOCUMENT]])
            ->willThrow(new ApiErrorException((object) ['error' => 'conflict', 'message' => 'A sync is in progress.']));
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('A configuration sync is already running for Source site site-a. Wait for it to finish, then push again.');
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
    }

    public function testOtherApiErrorPropagates(): void
    {
        $this->clientProphecy->request('put', '/source-sites/site-a/config', ['json' => ['configuration' => self::DOCUMENT]])
            ->willThrow(new ApiErrorException((object) ['error' => 'validation_failed', 'message' => (object) ['configuration' => 'Too large.']]));
        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Too large.');
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
    }

    public function testInvalidYamlNamesFile(): void
    {
        $this->fs->dumpFile($this->configDir . '/language/nl/system.site.yml', "name: 'unterminated\n");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('language/nl/system.site.yml is not valid YAML: Malformed inline YAML string');
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
    }

    public function testPollErrorThrows(): void
    {
        $this->mockPut();
        $this->clientProphecy->request('get', '/source-sites/site-a/config/import')
            ->willThrow(new ApiErrorException((object) ['error' => 'not_found', 'message' => 'No import yet.']))
            ->shouldBeCalled();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('The import was accepted but its outcome is unknown (No import yet.). Check the site before pushing again. The import may still finish, and the status it reports may then be that of a later import.');
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
    }

    public function testNonInteractiveWithoutForceThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Pass --force to push without confirmation when running non-interactively.');
        $this->executeCommand(['--site' => 'site-a'], [], interactive: false);
    }

    public function testDeclineDoesNotPush(): void
    {
        $this->executeCommand(['--site' => 'site-a'], ['n']);
        $this->assertSame(0, $this->getStatusCode());
    }

    public function testMissingConfigDirThrows(): void
    {
        $this->fs->remove($this->configDir);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage($this->configDir . ' does not exist. Run acli source:cms:config:pull first.');
        $this->executeCommand(['--site' => 'site-a', '--force' => true]);
    }
}
