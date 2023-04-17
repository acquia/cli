<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\App\LinkCommand $command
 */
class LinkCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(LinkCommand::class);
  }

  /**
   * Tests the 'link' command.
   */
  public function testLinkCommand(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application.
      0
    ];
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals($applications_response->{'_embedded'}->items[0]->uuid, $this->datastoreAcli->get('cloud_app_uuid'));
    $this->assertStringContainsString('There is no Cloud Platform application linked to', $output);
    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked', $output);
  }

  /**
   * Tests the 'link' command.
   */
  public function testLinkCommandAlreadyLinked(): void {
    $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');
    $this->mockApplicationRequest();
    $this->executeCommand([], []);
    $output = $this->getDisplay();
    $this->assertStringContainsString('This repository is already linked to Cloud application', $output);
    $this->assertEquals(1, $this->getStatusCode());
  }

  /**
   * Tests the 'link' command.
   */
  public function testLinkCommandInvalidDir(): void {
    $this->mockApplicationsRequest();
    $this->command->setProjectDir('');
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Could not find a local Drupal project.');
    $this->executeCommand([], []);
  }

}
