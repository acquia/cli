<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class TelemetryCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new TelemetryCommand();
  }

  /**
   * Tests the 'telemetry' command.
   */
  public function testTelemetryCommand(): void {
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
        ['y'],
        'Awesome! Thank you for helping!',
      ],
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
   */
  public function testTelemetryPrompt(array $input, $message): void {
    $this->removeMockAcliConfigFile();
    $this->setCommand($this->createCommand());
    $this->executeCommand([], $input);
    $output = $this->getDisplay();

    $this->assertStringContainsString('Would you like to share anonymous performance usage and data?', $output);
    $this->assertStringContainsString($message, $output);
  }

}
