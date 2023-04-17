<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Self\TelemetryDisableCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Self\TelemetryDisableCommand $command
 */
class TelemetryDisableCommandTest extends CommandTestBase {

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
