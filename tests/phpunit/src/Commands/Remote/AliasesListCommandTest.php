<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\AliasListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class AliasesListCommandTest.
 *
 * @property AliasListCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class AliasesListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AliasListCommand::class);
  }

  /**
   * Tests the 'remote:aliases:list' commands.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRemoteAliasesListCommand(): void {

    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select an Acquia Cloud application:
      '0',
      // Would you like to link the project at ...
      'n',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('| Sample application 1 | devcloud2.dev | 24-a47ac10b-58cc-4372-a567-0e02b2c3d470 |', $output);
  }

}
