<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Command\Self\TelemetryCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;

/**
 * @property \Acquia\Cli\Command\Self\TelemetryCommand $command
 */
class TelemetryCommandTest extends CommandTestBase {

  protected string $legacyAcliConfigFilepath;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->legacyAcliConfigFilepath = Path::join($this->dataDir, 'acquia-cli.json');
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  /**b
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryCommand::class);
  }

  /**
   * Tests the 'telemetry' command.
   */
  public function testTelemetryCommand(): void {
    $this->mockRequest('getAccount');
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been enabled.', $output);
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been disabled.', $output);
  }

  public function providerTestTelemetryPrompt(): array {
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
   * @param array $inputs
   * @param $message
   */
  public function testTelemetryPrompt(array $inputs, $message): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => NULL];
    $this->createMockConfigFiles();
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
  public function testAmplitudeDisabled(): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => FALSE];
    $this->createMockConfigFiles();
    $this->executeCommand();

    $this->assertEquals(0, $this->getStatusCode());
    $this->prophet->checkPredictions();
  }

  public function testMigrateLegacyTelemetryPreference(): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => NULL];
    $this->createMockConfigFiles();
    $this->fs->remove($this->legacyAcliConfigFilepath);
    $legacyAcliConfig = ['send_telemetry' => FALSE];
    $contents = json_encode($legacyAcliConfig);
    $this->fs->dumpFile($this->legacyAcliConfigFilepath, $contents);
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

}
