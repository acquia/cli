<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\Cli\Command\LinkCommand;
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
    return new LinkCommand();
  }

  /**
   * Tests the 'link' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testLinkCommand(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();
    // Request for applications.
    $applications_response = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $cloud_client->request('get', '/applications')->willReturn($applications_response->{'_embedded'}->items)->shouldBeCalled();
    $this->application->setAcquiaCloudClient($cloud_client->reveal());

    $inputs = [
      // Please select an Acquia Cloud application.
      0
    ];
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $acquia_cli_config = $this->command->getDatastore()->get($this->application->getAcliConfigFilename());
    $this->assertIsArray($acquia_cli_config);
    $this->assertArrayHasKey('localProjects', $acquia_cli_config);
    $this->assertArrayHasKey(0, $acquia_cli_config['localProjects']);
    $this->assertArrayHasKey('cloud_application_uuid', $acquia_cli_config['localProjects'][0]);
    $this->assertEquals($applications_response->{'_embedded'}->items[0]->uuid, $acquia_cli_config['localProjects'][0]['cloud_application_uuid']);
    $this->assertStringContainsString('Please select an Acquia Cloud application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('[1] Sample application 2', $output);
    $this->assertStringContainsString("The Cloud application with uuid {$applications_response->{'_embedded'}->items[0]->uuid} has been linked to the repository {$this->projectFixtureDir}", $output);
  }

}
