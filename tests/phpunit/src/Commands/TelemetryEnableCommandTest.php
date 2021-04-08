<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Command\TelemetryDisableCommand;
use Acquia\Cli\Command\TelemetryEnableCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryEnableCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryEnableCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryEnableCommandTest extends CommandTestBase {

  /**b
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryEnableCommand::class);
  }

  /**
   * Tests the 'telemetry:disable' command.
   */
  public function testTelemetryEnableCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been enabled.', $output);

    $settings = json_decode(file_get_contents($this->cloudConfigFilepath), TRUE);
    $this->assertTrue($settings['send_telemetry']);
  }

}
