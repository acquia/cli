<?php

namespace Acquia\Cli\Tests\Commands\Log;

use Acquia\Cli\Command\Log\LogListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class LogListCommandTest.
 *
 * @property \Acquia\Cli\Command\Log\LogListCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class LogListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new LogListCommand();
  }

  /**
   * Tests the 'log:list' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testLogListCommand(): void {
    $this->setCommand($this->createCommand());

    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);
    $this->mockLogListRequest();
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Select environment
      0,
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('Apache access', $output);
    $this->assertStringContainsString('Drupal request', $output);
  }

}
