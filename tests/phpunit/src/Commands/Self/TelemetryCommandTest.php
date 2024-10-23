<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Self;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\TelemetryCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Filesystem\Path;

/**
 * @property \Acquia\Cli\Command\Self\TelemetryCommand $command
 */
class TelemetryCommandTest extends CommandTestBase
{
    protected string $legacyAcliConfigFilepath;

    public function setUp(): void
    {
        parent::setUp();
        $this->legacyAcliConfigFilepath = Path::join($this->dataDir, 'acquia-cli.json');
        $this->fs->remove($this->legacyAcliConfigFilepath);
    }

    /**b
     */
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(TelemetryCommand::class);
    }

    /**
     * @group brokenProphecy
     */
    public function testTelemetryCommand(): void
    {
        $this->mockRequest('getAccount');
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Telemetry has been enabled.', $output);
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Telemetry has been disabled.', $output);
    }

    /**
     * @return string[][]
     */
    public static function providerTestTelemetryPrompt(): array
    {
        return [
            [
                // Would you like to share anonymous performance usage and data?
                ['n'],
                'Ok, no data will be collected and shared with us.',
            ],
        ];
    }

    /**
     * Tests telemetry prompt.
     *
     * @dataProvider providerTestTelemetryPrompt
     * @param $message
     */
    public function testTelemetryPrompt(array $inputs, mixed $message): void
    {
        $this->createMockCloudConfigFile([DataStoreContract::SEND_TELEMETRY => null]);
        $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');
        $this->createDataStores();
        $this->mockApplicationRequest();
        $this->command = $this->injectCommand(LinkCommand::class);
        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Would you like to share anonymous performance usage and data?', $output);
        $this->assertStringContainsString($message, $output);
    }

    /**
     * Opted out by default.
     */
    public function testAmplitudeDisabled(): void
    {
        $this->executeCommand();

        $this->assertEquals(0, $this->getStatusCode());
    }

    public function testMigrateLegacyTelemetryPreference(): void
    {
        $this->createMockCloudConfigFile([DataStoreContract::SEND_TELEMETRY => null]);
        $this->fs->remove($this->legacyAcliConfigFilepath);
        $legacyAcliConfig = ['send_telemetry' => false];
        $contents = json_encode($legacyAcliConfig);
        $this->fs->dumpFile($this->legacyAcliConfigFilepath, $contents);
        $this->executeCommand();

        $this->assertEquals(0, $this->getStatusCode());
        $this->fs->remove($this->legacyAcliConfigFilepath);
    }
}
