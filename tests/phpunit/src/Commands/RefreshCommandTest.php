<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class RefreshCommandTest.
 *
 * @property \Acquia\Cli\Command\RefreshCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class RefreshCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new LinkCommand();
  }

  /**
   * Tests the 'refresh' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRefreshCommand(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();
    $applications_response = $this->mockApplicationsRequest($cloud_client);
    $environments_response = $this->mockEnvironmentsRequest($cloud_client, $applications_response);


    $this->application->setAcquiaCloudClient($cloud_client->reveal());
  }

}
