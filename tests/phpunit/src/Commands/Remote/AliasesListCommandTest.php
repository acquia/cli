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
    return new AliasListCommand();
  }

  /**
   * Tests the 'remote:aliases:list' commands.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRemoteAliasesListCommand(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();

    $applications_response = $this->mockApplicationsRequest($cloud_client);
    $environments_response = $this->mockEnvironmentsRequest($cloud_client, $applications_response);
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[1]->uuid}/environments")
      ->willReturn([$environments_response])
      ->shouldBeCalled();
    $this->application->setAcquiaCloudClient($cloud_client->reveal());

    $inputs = [];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Fetching aliases for 2 applications from Acquia Cloud...', $output);
    $this->assertStringContainsString('| Sample application 1 | devcloud2.dev | 24-a47ac10b-58cc-4372-a567-0e02b2c3d470 |', $output);
    $this->assertStringContainsString('| Sample application 2 | devcloud2.dev | 24-a47ac10b-58cc-4372-a567-0e02b2c3d470 |', $output);
  }

}
