<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeListCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdeListCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeListCommand::class);
  }

  /**
   * Tests the 'ide:list' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeListCommand(): void {

    $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('IDE Label 1', $output);
    $this->assertStringContainsString('Web URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.web.ahdev.cloud', $output);
    $this->assertStringContainsString('IDE URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.ides.acquia.com', $output);
    $this->assertStringContainsString('IDE Label 2', $output);
    $this->assertStringContainsString('Web URL: https://feea197a-9503-4441-9f49-b4d420b0ecf8.web.ahdev.cloud', $output);
    $this->assertStringContainsString('IDE URL: https://feea197a-9503-4441-9f49-b4d420b0ecf8.ides.acquia.com', $output);
  }

}
