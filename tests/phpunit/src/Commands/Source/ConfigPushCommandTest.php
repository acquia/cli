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
use Throwable;

/**
 * @property ConfigPushCommand $command
 */
class ConfigPushCommandTest extends CommandTestBase
{
    /**
     * The site UUID in system.site is what an import checks first; it is not
     * the site ID the commands address.
     */
    private const DOCUMENT = <<<'YAML'
        '':
          system.site:
            name: Site
            uuid: 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70
        language.nl:
          system.site:
            name: Website

        YAML;

    /**
     * The import that was already on the site before the push. It failed, so a
     * push that mistakes it for its own would exit 1 instead of 0.
     */
    private const PREVIOUS_ID = 'i-previous';

    private const ID = 'i-this-push';

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
        $this->clientProphecy->request('put', '/source-sites/site-a/config', ['json' => ['source' => 'request', 'configuration' => self::DOCUMENT]])
            ->willReturn((object) ['message' => 'accepted'])
            ->shouldBeCalled();
    }

    /**
     * @param array<object|\Throwable> $responses One per GET of the import resource
     *   in order; the last one repeats. A push GETs it once before the PUT, then
     *   on every poll.
     */
    private function mockImport(array $responses): void
    {
        $this->clientProphecy->request('get', '/source-sites/site-a/config/import')
            ->will(static function () use (&$responses): object {
                $response = count($responses) > 1 ? array_shift($responses) : $responses[0];
                if ($response instanceof Throwable) {
                    throw $response;
                }
                return $response;
            })
            ->shouldBeCalled();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function import(string $status, string $id = self::ID, array $extra = []): object
    {
        return (object) ($extra + ['id' => $id, 'status' => $status]);
    }

    /**
     * The push's own import, preceded by the site's previous (failed) one.
     *
     * @return array<object>
     */
    private static function afterPrevious(object ...$imports): array
    {
        return [self::import('failed', self::PREVIOUS_ID), ...$imports];
    }

    public function testSucceeds(): void
    {
        $this->mockPut();
        $this->mockImport(self::afterPrevious(self::import('running'), self::import('succeeded')));
        $this->executeCommand(['--site' => 'site-a', '--yes' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Imported .acquia/config into Source site site-a.', $this->getDisplay());
    }

    public function testIgnoresPreviousImportUntilOwnStarts(): void
    {
        $this->mockPut();
        // The first poll still sees the previous import: the service has not
        // created this push's record yet.
        $this->mockImport(self::afterPrevious(self::import('failed', self::PREVIOUS_ID), self::import('succeeded')));
        $this->executeCommand(['--site' => 'site-a', '--yes' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Imported .acquia/config into Source site site-a.', $this->getDisplay());
        $this->assertStringNotContainsString('failed', $this->getDisplay());
    }

    public function testImportsAreToldApartByIdNotStartedAt(): void
    {
        $this->mockPut();
        $startedAt = ['started_at' => '2026-10-21T09:00:00Z'];
        $this->mockImport([
            self::import('failed', self::PREVIOUS_ID, $startedAt),
            self::import('succeeded', self::ID, $startedAt),
        ]);
        $this->executeCommand(['--site' => 'site-a', '--yes' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Imported .acquia/config into Source site site-a.', $this->getDisplay());
    }

    public function testFirstEverImport(): void
    {
        $this->mockPut();
        $notFound = new ApiErrorException((object) ['error' => 'not_found', 'message' => 'No import yet.']);
        // No record before the PUT, and still none at the first poll.
        $this->mockImport([$notFound, $notFound, self::import('succeeded')]);
        $this->executeCommand(['--site' => 'site-a', '--yes' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Imported .acquia/config into Source site site-a.', $this->getDisplay());
    }

    public function testErrorBeforePutPropagatesWithoutPushing(): void
    {
        $this->mockImport([new ApiErrorException((object) ['error' => 'internal_error', 'message' => 'Boom.'])]);
        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Boom.');
        $this->executeCommand(['--site' => 'site-a', '--yes' => true], [], interactive: false);
    }

    public function testNoNewImportBeforeTimeoutThrows(): void
    {
        $this->mockPut();
        $this->mockImport(self::afterPrevious());
        // 45 minutes in production; polls run at 0.1 s and then every 5 s.
        (new ReflectionProperty(ConfigPushCommand::class, 'importTimeout'))->setValue($this->command, 0.6);
        try {
            $this->executeCommand(['--site' => 'site-a', '--yes' => true], [], interactive: false);
            $this->fail('Expected an exception');
        } catch (AcquiaCliException $e) {
            $this->assertSame('The import was accepted but its outcome is unknown (the site reported no new import before the timeout). Run acli source:cms:config:push --status to see the site\'s latest import before pushing again. The import may still finish, and the status it reports may then be that of a later import.', $e->getMessage());
        }
        // LoopHelper reports the timeout in minutes: 0.6 s.
        $this->assertStringContainsString('Timed out after 0.01 minutes!', $this->getDisplay());
    }

    public function testRefusedListsViolations(): void
    {
        $this->mockPut();
        $this->mockImport(self::afterPrevious(self::import('refused', extra: [
            'violations' => [
                (object) ['code' => 'not_allowed', 'collection' => 'language.nl', 'config' => 'system.site', 'message' => 'Not in the allow list.'],
                (object) ['code' => 'missing', 'config' => 'system.site', 'message' => 'Required.'],
                (object) ['code' => 'site_uuid_mismatch', 'message' => 'The configuration was exported from site 7c1f0a94-5d3b-4e18-9a62-0b8d4c5e6f70, not from this site.'],
            ],
        ])));
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
        $import = self::import('refused', extra: ['violations' => [(object) ['code' => 'too_large', 'message' => 'Too large.']]]);
        $this->mockImport(self::afterPrevious($import));
        $this->executeCommand(['--site' => 'site-a', '--yes' => true, '--format' => 'json']);
        $this->assertSame(1, $this->getStatusCode());
        $this->assertSame(json_encode($import, JSON_PRETTY_PRINT) . "\n", $this->getDisplay());
    }

    public function testFailed(): void
    {
        $this->mockPut();
        $this->mockImport(self::afterPrevious(self::import('failed')));
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
        $this->assertSame(1, $this->getStatusCode());
        // The error block wraps, so assert the two halves separately.
        $this->assertStringContainsString('The import into Source site site-a failed; the site was rolled back to', $this->getDisplay());
        $this->assertStringContainsString('previous configuration.', $this->getDisplay());
    }

    public function testUnknownStatusThrows(): void
    {
        $this->mockPut();
        $this->mockImport(self::afterPrevious(self::import('weird')));
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('The import was accepted but its outcome is unknown (status weird). Run acli source:cms:config:push --status to see the site\'s latest import before pushing again. The import may still finish, and the status it reports may then be that of a later import.');
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
    }

    public function testStatusReportsSucceededWithoutPushing(): void
    {
        $this->mockImport([(object) ['id' => 'i1', 'status' => 'succeeded', 'source' => 'github', 'started_at' => '2026-09-09T10:30:00Z', 'finished_at' => '2026-09-09T10:32:41Z']]);
        $this->executeCommand(['--site' => 'site-a', '--status' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
        $display = $this->getDisplay();
        $this->assertStringContainsString("Source site site-a's latest import started at 2026-09-09T10:30:00Z (source: GitHub commit).", $display);
        $this->assertStringContainsString('Imported .acquia/config into Source site site-a.', $display);
    }

    public function testStatusReportsRefusedListsViolations(): void
    {
        $this->mockImport([(object) [
            'finished_at' => '2026-09-09T10:32:41Z',
            'id' => 'i1',
            'source' => 'request',
            'started_at' => '2026-09-09T10:30:00Z',
            'status' => 'refused',
            'violations' => [
                (object) ['code' => 'not_allowed', 'collection' => 'language.nl', 'config' => 'system.site', 'message' => 'Not in the allow list.'],
            ],
        ],
        ]);
        $this->executeCommand(['--site' => 'site-a', '--status' => true], [], interactive: false);
        $this->assertSame(1, $this->getStatusCode());
        $display = $this->getDisplay();
        $this->assertStringContainsString("Source site site-a's latest import started at 2026-09-09T10:30:00Z (source: ACLI request).", $display);
        $this->assertStringContainsString('Source site site-a refused the configuration; nothing was imported:', $display);
        $this->assertStringContainsString(' - language.nl: system.site [not_allowed]: Not in the allow list.', $display);
    }

    public function testStatusReportsFailed(): void
    {
        $this->mockImport([(object) ['id' => 'i1', 'status' => 'failed', 'source' => 'request', 'started_at' => '2026-09-09T10:30:00Z', 'finished_at' => '2026-09-09T10:32:41Z']]);
        $this->executeCommand(['--site' => 'site-a', '--status' => true], [], interactive: false);
        $this->assertSame(1, $this->getStatusCode());
        $this->assertStringContainsString("Source site site-a's latest import started at 2026-09-09T10:30:00Z (source: ACLI request).", $this->getDisplay());
        $this->assertStringContainsString('The import into Source site site-a failed; the site was rolled back to', $this->getDisplay());
    }

    public function testStatusReportsRunningWithDistinctExitCode(): void
    {
        $this->mockImport([(object) ['id' => 'i1', 'status' => 'running', 'source' => 'request', 'started_at' => '2026-09-09T10:30:00Z', 'finished_at' => null]]);
        $this->executeCommand(['--site' => 'site-a', '--status' => true], [], interactive: false);
        $this->assertSame(2, $this->getStatusCode());
        $display = $this->getDisplay();
        $this->assertStringContainsString("Source site site-a's latest import started at 2026-09-09T10:30:00Z (source: ACLI request)", $display);
        $this->assertStringContainsString('still running', $display);
    }

    public function testStatusJsonOutput(): void
    {
        $import = (object) ['id' => 'i1', 'status' => 'refused', 'source' => 'request', 'started_at' => '2026-09-09T10:30:00Z', 'finished_at' => '2026-09-09T10:32:41Z', 'violations' => [(object) ['code' => 'too_large', 'message' => 'Too large.']]];
        $this->mockImport([$import]);
        $this->executeCommand(['--site' => 'site-a', '--status' => true, '--format' => 'json'], [], interactive: false);
        $this->assertSame(1, $this->getStatusCode());
        $this->assertSame(json_encode($import, JSON_PRETTY_PRINT) . "\n", $this->getDisplay());
    }

    public function testStatusDoesNotRequireConfigDirOrConfirmation(): void
    {
        $this->fs->remove($this->configDir);
        $this->mockImport([(object) ['id' => 'i1', 'status' => 'succeeded', 'source' => 'request', 'started_at' => '2026-09-09T10:30:00Z', 'finished_at' => '2026-09-09T10:32:41Z']]);
        $this->executeCommand(['--site' => 'site-a', '--status' => true], [], interactive: false);
        $this->assertSame(0, $this->getStatusCode());
    }

    public function testConflictThrows(): void
    {
        $this->mockImport(self::afterPrevious());
        $this->clientProphecy->request('put', '/source-sites/site-a/config', ['json' => ['source' => 'request', 'configuration' => self::DOCUMENT]])
            ->willThrow(new ApiErrorException((object) ['error' => 'conflict', 'message' => 'A sync is in progress.']));
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('A configuration sync is already running for Source site site-a. Wait for it to finish, then push again.');
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
    }

    public function testOtherApiErrorPropagates(): void
    {
        $this->mockImport(self::afterPrevious());
        $this->clientProphecy->request('put', '/source-sites/site-a/config', ['json' => ['source' => 'request', 'configuration' => self::DOCUMENT]])
            ->willThrow(new ApiErrorException((object) ['error' => 'validation_failed', 'message' => (object) ['configuration' => 'Too large.']]));
        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Too large.');
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
    }

    public function testInvalidYamlNamesFile(): void
    {
        $this->fs->dumpFile($this->configDir . '/language/nl/system.site.yml', "name: 'unterminated\n");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('language/nl/system.site.yml is not valid YAML: Malformed inline YAML string');
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
    }

    public function testPollErrorThrows(): void
    {
        $this->mockPut();
        $this->mockImport(self::afterPrevious(new ApiErrorException((object) ['error' => 'internal_error', 'message' => 'Boom.'])));
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('The import was accepted but its outcome is unknown (Boom.). Run acli source:cms:config:push --status to see the site\'s latest import before pushing again. The import may still finish, and the status it reports may then be that of a later import.');
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
    }

    public function testInvalidSiteOptionThrowsBeforeRequesting(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('"a/../b" is not a valid Source site ID: only letters, digits and hyphens are allowed.');
        $this->executeCommand(['--site' => 'a/../b', '--yes' => true]);
    }

    public function testNonInteractiveWithoutForceThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Pass --yes to push without confirmation when running non-interactively.');
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
        $this->executeCommand(['--site' => 'site-a', '--yes' => true]);
    }
}
