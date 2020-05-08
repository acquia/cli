<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
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
    return new IdeListCommand();
  }

  /**
   * Tests the 'ide:list' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeListCommand(): void {
    $this->setCommand($this->createCommand());

    /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
    $cloud_client = $this->prophet->prophesize(Client::class);

    // Request for applications.
    $response = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $cloud_client->request('get', '/applications')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();

    // Request to list IDEs.
    $response = $this->getMockResponseFromSpec('/api/applications/{applicationUuid}/ides', 'get', '200');
    $cloud_client->request('get', '/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470/ides')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();

    $inputs = [
          // Please select the application..
      '0',
    ];

   $this->application->setAcquiaCloudClient($cloud_client->reveal());
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
