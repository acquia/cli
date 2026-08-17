<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\ConfigPullCommand;
use Acquia\Cli\SasApi\SasClient;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @property ConfigPullCommand $command
 */
class ConfigPullCommandTest extends CommandTestBase
{
    private const SITE_ID = '0ebce493-9d09-479d-a9a8-138a206fa687';
    private const ENVIRONMENT_ID = '3e8ecbec-ea7c-4260-8414-ef2938c859bc';
    private const SITE_INSTANCE_ID = self::SITE_ID . '.' . self::ENVIRONMENT_ID;

    private SasClientService|ObjectProphecy $sasClientServiceProphecy;

    private SasClient|ObjectProphecy $sasClientProphecy;

    protected function createCommand(): CommandBase
    {
        $this->sasClientProphecy = $this->prophet->prophesize(SasClient::class);
        $this->sasClientServiceProphecy = $this->prophet->prophesize(SasClientService::class);
        $this->sasClientServiceProphecy->getClient()->willReturn($this->sasClientProphecy->reveal());

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
            $this->sasClientServiceProphecy->reveal(),
        );
    }

    /**
     * Mock the Cloud API calls needed to resolve a site instance from
     * --siteInstanceId: environment, site, site instance, and codebase.
     */
    private function mockSiteInstanceResolution(): void
    {
        $environment = $this->getMockCodeBaseEnvironment();
        $this->clientProphecy->request('get', '/v3/environments/' . self::ENVIRONMENT_ID)
            ->willReturn($environment)
            ->shouldBeCalled();

        $site = $this->getMockSite();
        $this->clientProphecy->request('get', '/sites/' . self::SITE_ID)
            ->willReturn($site)
            ->shouldBeCalled();

        $siteInstance = $this->getMockSiteInstanceResponse();
        $this->clientProphecy->request('get', '/site-instances/' . self::SITE_INSTANCE_ID)
            ->willReturn($siteInstance)
            ->shouldBeCalled();

        $codebase = $this->getMockCodebaseResponse();
        $this->clientProphecy->request('get', '/codebases/d3f7270e-c45f-4801-9308-5e8afe84a323')
            ->willReturn($codebase)
            ->shouldBeCalled();
    }

    public function testExecutePullsAndWritesPayload(): void
    {
        $this->mockSiteInstanceResolution();

        $this->sasClientProphecy->request('post', '/environments/' . self::ENVIRONMENT_ID . '/config-export')
            ->willReturn((object) ['id' => 'operation-123'])
            ->shouldBeCalled();

        $this->sasClientProphecy->request('get', '/config-operation/operation-123')
            ->willReturn((object) ['status' => 'succeeded'])
            ->shouldBeCalled();

        // The payload endpoint returns the exported config as a YAML document.
        $yaml = "\"\":\n  system.site:\n    name: 'My Site'\n";
        $this->sasClientProphecy->request('get', '/config-operation/operation-123/payload')
            ->willReturn((object) ['payload' => $yaml])
            ->shouldBeCalled();

        $this->executeCommand(
            ['--siteInstanceId' => self::SITE_INSTANCE_ID, '--force' => true],
        );

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Configuration exported', $this->getDisplay());
        // The payload was written to disk.
        $this->assertFileExists($this->projectDir . '/.acquia/config/system.site.yml');
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
        $this->assertStringEqualsFile($configDir . '/node.type.blog.yml', "label: Blog\n");
        $this->assertStringEqualsFile($configDir . '/system.site.yml', "name: 'My Site'\n");
        $this->assertStringEqualsFile($configDir . '/language/es/node.type.blog.yml', "label: Blogue\n");
    }

    public function testWritePayloadWipesExistingConfig(): void
    {
        $configDir = $this->projectDir . '/.acquia/config';
        mkdir($configDir, 0777, true);
        file_put_contents($configDir . '/stale.setting.yml', "old: true\n");

        $this->writePayload($this->projectDir, [
            '' => ['system.site' => ['name' => 'My Site']],
        ]);

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
