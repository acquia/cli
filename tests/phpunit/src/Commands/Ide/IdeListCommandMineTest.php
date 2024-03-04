<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeListMineCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Ide\IdeListMineCommand $command
 */
class IdeListCommandMineTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(IdeListMineCommand::class);
  }

  public function testIdeListMineCommand(): void {
    $applicationsResponse = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $idesResponse = $this->mockAccountIdeListRequest();
    foreach ($idesResponse->{'_embedded'}->items as $key => $ide) {
      $applicationResponse = $applicationsResponse->{'_embedded'}->items[$key];
      $appUrlParts = explode('/', $ide->_links->application->href);
      $appUuid = end($appUrlParts);
      $applicationResponse->uuid = $appUuid;
      $this->clientProphecy->request('get', '/applications/' . $appUuid)
        ->willReturn($applicationResponse)
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
