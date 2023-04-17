<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Self\TelemetryEnableCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class TelemetryEnableCommandTest.
 *
 * @property \Acquia\Cli\Command\Self\TelemetryEnableCommand $command
 */
class TelemetryEnableCommandTest extends CommandTestBase {

  /**b
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryEnableCommand::class);
  }

  /**
   * Tests the 'telemetry:enable' command.
   */
  public function testTelemetryEnableCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been enabled.', $output);

    $settings = json_decode(file_get_contents($this->cloudConfigFilepath), TRUE);
    $this->assertTrue($settings['send_telemetry']);
  }

}
