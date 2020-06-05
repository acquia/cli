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
      // Please select an Acquia Cloud application.
      0
    ];
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $acquia_cli_config = $this->acliDatastore->get($this->acliConfigFilename);
    $this->assertIsArray($acquia_cli_config);
    $this->assertArrayHasKey('localProjects', $acquia_cli_config);
    $this->assertArrayHasKey(0, $acquia_cli_config['localProjects']);
    $this->assertArrayHasKey('cloud_application_uuid', $acquia_cli_config['localProjects'][0]);
    $this->assertEquals($applications_response->{'_embedded'}->items[0]->uuid, $acquia_cli_config['localProjects'][0]['cloud_application_uuid']);
    $this->assertStringContainsString('Please select an Acquia Cloud application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked to this repository', $output);
  }

  /**
   * Tests the 'link' command.
   *
   * @throws \Exception
   */
  public function testLinkCommandInvalidDir(): void {
    $this->setCommand($this->createCommand());
    try {
      $this->executeCommand([], []);
    }
    catch (AcquiaCliException $e) {
      $this->assertEquals('Could not find a local Drupal project.', $e->getMessage());
    }
  }

}
