<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryCommandTest extends CommandTestBase {

  /**
   * @var string
   */
  protected $legacyAcliConfigFilepath;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->legacyAcliConfigFilepath = Path::join($this->dataDir, 'acquia-cli.json');
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  /**b
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryCommand::class);
  }

  /**
   * Tests the 'telemetry' command.
   */
  public function testTelemetryCommand(): void {
    $this->mockAccountRequest();
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
   *
   * @param array $inputs
   * @param $message
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testTelemetryPrompt(array $inputs, $message): void {
    $this->command = $this->injectCommand(LinkCommand::class);
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => NULL];
    $this->createMockConfigFiles();
    $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');
    $this->mockApplicationRequest();
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();

    $this->assertStringContainsString('Would you like to share anonymous performance usage and data?', $output);
    $this->assertStringContainsString($message, $output);
  }

  /**
   * Opted out by default.
   * @throws \Exception
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
    $legacy_acli_config = ['send_telemetry' => FALSE];
    $contents = json_encode($legacy_acli_config);
    $this->fs->dumpFile($this->legacyAcliConfigFilepath, $contents);
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

}
