<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\LogTailCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\App\LogTailCommand $command
 */
class LogTailCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(LogTailCommand::class);
  }

  public function testLogTailCommand(): void {
    $this->mockGetEnvironment();
    $this->mockLogStreamRequest();
    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Select environment
      0,
      // Select log
      0,
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('Apache request', $output);
    $this->assertStringContainsString('Drupal request', $output);
  }

  public function testLogTailCommandWithEnvArg(): void {
    $this->mockLogStreamRequest();
    $this->executeCommand(
      ['environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470'],
      // Select log.
      [0]
    );

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Apache request', $output);
    $this->assertStringContainsString('Drupal request', $output);
  }

}
