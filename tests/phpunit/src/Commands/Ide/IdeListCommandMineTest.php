<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeListMineCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeListCommandMineTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdeListMineCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeListCommandMineTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(IdeListMineCommand::class);
  }

  /**
   * Tests the 'ide:list-mine' commands.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeListMineCommand(): void {
    $applications_response = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $ides_response = $this->mockAccountIdeListRequest();
    foreach ($ides_response->{'_embedded'}->items as $key => $ide) {
      $application_response = $applications_response->{'_embedded'}->items[$key];
      $app_url_parts = explode('/', $ide->_links->application->href);
      $app_uuid = end($app_url_parts);
      $application_response->uuid = $app_uuid;
      $this->clientProphecy->request('get', '/applications/' . $app_uuid)
        ->willReturn($application_response)
        ->shouldBeCalled();
    }

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('IDE Label 1', $output);
    $this->assertStringContainsString('UUID: 9a83c081-ef78-4dbd-8852-11cc3eb248f7', $output);
    $this->assertStringContainsString('Application: Sample application 1', $output);
    $this->assertStringContainsString('Subscription: Sample subscription', $output);
    $this->assertStringContainsString('IDE URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.ide.ahdev.cloud', $output);
    $this->assertStringContainsString('Web URL: https://9a83c081-ef78-4dbd-8852-11cc3eb248f7.web.ahdev.cloud', $output);

    $this->assertStringContainsString('IDE Label 2', $output);
    $this->assertStringContainsString('UUID: 9a83c081-ef78-4dbd-8852-11cc3eb248f7', $output);
    $this->assertStringContainsString('Application: Sample application 2', $output);
    $this->assertStringContainsString('Subscription: Sample subscription', $output);
    $this->assertStringContainsString('IDE URL: https://feea197a-9503-4441-9f49-b4d420b0ecf8.ide.ahdev.cloud', $output);
    $this->assertStringContainsString('Web URL: https://feea197a-9503-4441-9f49-b4d420b0ecf8.web.ahdev.cloud', $output);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockAccountIdeListRequest(): object {
    $response = $this->getMockResponseFromSpec('/account/ides',
      'get', '200');
    $this->clientProphecy->request('get',
      '/account/ides')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();

    return $response;
  }

}
