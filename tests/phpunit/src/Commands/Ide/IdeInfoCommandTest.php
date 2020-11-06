<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeInfoCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeListCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdeListCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeInfoCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeInfoCommand::class);
  }

  /**
   * Tests the 'ide:info' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeInfoCommand(): void {

    $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $response = $this->mockIdeListRequest();
    $this->mockGetIdeRequest($response->_embedded->items[0]->uuid);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Please select an IDE ...
      0
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('IDE property        IDE value', $output);
    $this->assertStringContainsString('UUID                215824ff-272a-4a8c-9027-df32ed1d68a9', $output);
    $this->assertStringContainsString('Label               Example IDE', $output);
  }

}
