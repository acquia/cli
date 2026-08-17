<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPushCommand;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @property ConfigPushCommand $command
 */
class ConfigPushCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $sasClientService = $this->prophet->prophesize(SasClientService::class);

        return new ConfigPushCommand(
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
     * Invoke the private payload assembler against a directory of config files.
     *
     * @return array<string, array<string, mixed>>
     */
    private function assemblePayload(string $dir): array
    {
        (new ReflectionProperty($this->command, 'dir'))->setValue($this->command, $dir);
        $method = new ReflectionMethod($this->command, 'assemblePayload');

        return $method->invoke($this->command);
    }

    public function testAssemblePayloadBuildsCollectionsFromDirectories(): void
    {
        $configDir = $this->projectDir . '/.acquia/config';
        mkdir($configDir . '/language/es', 0777, true);
        file_put_contents($configDir . '/node.type.blog.yml', "label: Blog\n");
        file_put_contents($configDir . '/system.site.yml', "name: My Site\n");
        file_put_contents($configDir . '/language/es/node.type.blog.yml', "label: Blogue\n");

        $payload = $this->assemblePayload($this->projectDir);

        // The root directory maps to the default ("") collection, and the
        // language/es subdirectory maps to the language.es collection.
        $this->assertSame(['label' => 'Blog'], $payload['']['node.type.blog']);
        $this->assertSame(['name' => 'My Site'], $payload['']['system.site']);
        $this->assertSame(['label' => 'Blogue'], $payload['language.es']['node.type.blog']);
    }

    public function testAssemblePayloadReturnsEmptyArrayWhenDirectoryMissing(): void
    {
        $this->assertSame([], $this->assemblePayload($this->projectDir));
    }

    public function testAssemblePayloadOmitsEmptyCollections(): void
    {
        $configDir = $this->projectDir . '/.acquia/config';
        mkdir($configDir . '/language/fr', 0777, true);
        file_put_contents($configDir . '/system.site.yml', "name: My Site\n");

        $payload = $this->assemblePayload($this->projectDir);

        // The language/fr directory holds no .yml files, so no language.fr
        // collection appears in the payload.
        $this->assertArrayNotHasKey('language.fr', $payload);
        $this->assertSame(['name' => 'My Site'], $payload['']['system.site']);
    }
}
