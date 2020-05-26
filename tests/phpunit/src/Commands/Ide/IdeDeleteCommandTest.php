<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeDeleteCommandTest.
 *
 * @property IdeDeleteCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeDeleteCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new IdeDeleteCommand();
  }

  /**
   * Tests the 'ide:delete' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeDeleteCommand(): void {
    $this->setCommand($this->createCommand());
    $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();

    // Request to delete IDE.
    $response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'delete', '202');
    $this->clientProphecy->request(
          'delete',
          '/ides/9a83c081-ef78-4dbd-8852-11cc3eb248f7'
      )->willReturn($response->{"De-provisioning IDE"}->value)
      ->shouldBeCalled();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application for which you'd like to create a new IDE.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Please select the IDE you'd like to delete:
      0,
    ];

    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('The remote IDE is being deleted.', $output);
  }

}
