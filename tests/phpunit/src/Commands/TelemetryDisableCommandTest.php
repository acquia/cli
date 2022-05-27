<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Self\TelemetryDisableCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class TelemetryDisableCommandTest.
 *
 * @property \Acquia\Cli\Command\Self\TelemetryDisableCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryDisableCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryDisableCommand::class);
  }

  /**
   * Tests the 'telemetry:disable' command.
   */
  public function testTelemetryDisableCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been disabled.', $output);

    $settings = json_decode(file_get_contents($this->cloudConfigFilepath), TRUE);
    $this->assertFalse($settings['send_telemetry']);
  }

}
