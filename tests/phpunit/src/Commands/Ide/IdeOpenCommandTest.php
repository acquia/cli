<?php

namespace Acquia\Ads\Tests\Commands\Ide;

use Acquia\Ads\AcquiaCliApplication;
use Acquia\Ads\Command\Ide\IdeOpenCommand;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeOpenCommandTest.
 *
 * @property IdeOpenCommand $command
 * @package Acquia\Ads\Tests\Ide
 */
class IdeOpenCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new IdeOpenCommand();
  }

  /**
   * Tests the 'ide:open' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeOpenCommand(): void {
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
      '0',
    ];

    AcquiaCliApplication::setAcquiaCloudClient($cloud_client->reveal());
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Please select the IDE you\'d like to open:', $output);
    $this->assertStringContainsString('[0] IDE Label 1', $output);
    $this->assertStringContainsString('Your IDE URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.ides.acquia.com', $output);
    $this->assertStringContainsString('Your Drupal Site URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.web.ahdev.cloud', $output);
    $this->assertStringContainsString('Opening your IDE in browser...', $output);
  }

}
