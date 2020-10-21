<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class LinkCommandTest.
 *
 * @property \Acquia\Cli\Command\LinkCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class LinkCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(LinkCommand::class);
  }

  /**
   * Tests the 'link' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   */
  public function testLinkCommand(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application.
      0
    ];
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals($applications_response->{'_embedded'}->items[0]->uuid, $this->datastoreAcli->get('cloud_app_uuid'));
    $this->assertStringContainsString('There is no Cloud Platform application linked to', $output);
    $this->assertStringContainsString('Please select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked', $output);
  }

  /**
   * Tests the 'link' command.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testLinkCommandInvalidDir(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->command->setRepoRoot('');
    try {
      $this->executeCommand([], []);
    }
    catch (AcquiaCliException $e) {
      $this->assertStringContainsString('Could not find a local Drupal project.', $e->getMessage());
    }
  }

}
