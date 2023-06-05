<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeOpenCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property IdeOpenCommand $command
 */
class IdeOpenCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(IdeOpenCommand::class);
  }

  public function setUp(mixed $output = NULL): void {
    parent::setUp();
    putenv('DISPLAY=1');
  }

  public function tearDown(): void {
    parent::tearDown();
    putenv('DISPLAY');
  }

  public function testIdeOpenCommand(): void {

    $this->mockRequest('getApplications');
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'y',
      // Select the IDE you'd like to open:
      0,
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Select the IDE you\'d like to open:', $output);
    $this->assertStringContainsString('[0] IDE Label 1', $output);
    $this->assertStringContainsString('Your IDE URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.ides.acquia.com', $output);
    $this->assertStringContainsString('Your Drupal Site URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.web.ahdev.cloud', $output);
    $this->assertStringContainsString('Opening your IDE in browser...', $output);
  }

}
