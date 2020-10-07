<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LogTailCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class LogTailCommandTest.
 *
 * @property \Acquia\Cli\Command\LogTailCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class LogTailCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(LogTailCommand::class);
  }

  /**
   * Tests the 'log:tail' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testLogTailCommand(): void {

    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);
    $this->mockLogListRequest();
    $this->mockLogStreamRequest();
    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Select environment
      0,
      // Select log
      0
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('Apache access', $output);
    $this->assertStringContainsString('Drupal request', $output);
  }

  /**
   * Tests the 'log:tail' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testLogTailCommandWithEnvArg(): void {
    $this->mockLogListRequest();
    $this->mockLogStreamRequest();
    $this->executeCommand(
      ['environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470'],
      // Select log.
      [0]
    );

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Apache access', $output);
    $this->assertStringContainsString('Drupal request', $output);
  }

}
