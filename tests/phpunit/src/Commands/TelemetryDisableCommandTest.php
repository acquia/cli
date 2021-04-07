<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Command\TelemetryDisableCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryDisableCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryDisableCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryDisableCommandTest extends TelemetryCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryDisableCommand::class);
  }

  /**
   * Tests the 'telemetry:disable' command.
   */
  public function testTelemetryCommand(): void {
    $account = json_decode(file_get_contents(Path::join($this->fixtureDir, '/account.json')));
    $this->clientProphecy->request('get', '/account')
      ->willReturn($account);

    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been disabled.', $output);

    $settings = json_decode(file_get_contents($this->cloudConfigFilepath), TRUE);
    $this->assertFalse($settings['send_telemetry']);
  }

}
