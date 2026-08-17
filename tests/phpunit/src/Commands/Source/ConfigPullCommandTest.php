<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPullCommand;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @property ConfigPullCommand $command
 */
class ConfigPullCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $sasClientService = $this->prophet->prophesize(SasClientService::class);

        return new ConfigPullCommand(
            $this->localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->acliRepoRoot,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
            $sasClientService->reveal(),
        );
    }

    /**
     * Invoke the private payload writer against a directory.
     *
     * @param array<string, array<string, mixed>> $payload
     */
    private function writePayload(string $dir, array $payload): void
    {
        (new ReflectionProperty($this->command, 'dir'))->setValue($this->command, $dir);
        $method = new ReflectionMethod($this->command, 'writePayload');
        $method->invoke($this->command, $payload);
    }

    public function testWritePayloadBuildsFilesFromCollections(): void
    {
        $payload = [
            '' => [
                'node.type.blog' => ['label' => 'Blog'],
                'system.site' => ['name' => 'My Site'],
            ],
            'language.es' => [
                'node.type.blog' => ['label' => 'Blogue'],
            ],
        ];

        $this->writePayload($this->projectDir, $payload);

        $configDir = $this->projectDir . '/.acquia/config';
        // The default collection writes to the config root; the language.es
        // collection writes to the language/es subdirectory.
        $this->assertStringEqualsFile($configDir . '/node.type.blog.yml', "label: Blog\n");
        $this->assertStringEqualsFile($configDir . '/system.site.yml', "name: 'My Site'\n");
        $this->assertStringEqualsFile($configDir . '/language/es/node.type.blog.yml', "label: Blogue\n");
    }

    public function testWritePayloadWipesExistingConfig(): void
    {
        $configDir = $this->projectDir . '/.acquia/config';
        mkdir($configDir, 0777, true);
        // A stale file that no longer exists in the remote payload.
        file_put_contents($configDir . '/stale.setting.yml', "old: true\n");

        $this->writePayload($this->projectDir, [
            '' => ['system.site' => ['name' => 'My Site']],
        ]);

        // The stale file is removed; only the payload's files remain.
        $this->assertFileDoesNotExist($configDir . '/stale.setting.yml');
        $this->assertStringEqualsFile($configDir . '/system.site.yml', "name: 'My Site'\n");
    }

    public function testWritePayloadSkipsNonArrayCollections(): void
    {
        $this->writePayload($this->projectDir, [
            '' => ['system.site' => ['name' => 'My Site']],
            'malformed' => 'not-an-array',
        ]);

        $configDir = $this->projectDir . '/.acquia/config';
        $this->assertFileExists($configDir . '/system.site.yml');
        $this->assertFileDoesNotExist($configDir . '/malformed');
    }
}
