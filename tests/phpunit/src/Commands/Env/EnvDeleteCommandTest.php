<?php

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\Env\EnvDeleteCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class DeleteCdeCommandTest.
 *
 * @property \Acquia\Cli\Command\Env\EnvDeleteCommand $command
 */
class EnvDeleteCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(EnvDeleteCommand::class);
  }

  /**
   * @return array
   */
  public function providerTestDeleteCde(): array {
    $environment_response = $this->getMockEnvironmentsResponse();
    $environment = $environment_response->_embedded->items[0];
    return [
      [$environment->id],
      [NULL],
    ];
  }

  /**
   * Tests the 'app:environment:delete' command.
   *
   * @dataProvider providerTestDeleteCde
   */
  public function testDeleteCde($environment_id): void {
    $applications_response = $this->mockApplicationsRequest();
    $application_response = $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);

    $response1 = $this->getMockEnvironmentsResponse();
    $response2 = $this->getMockEnvironmentsResponse();
    $cde = $response2->_embedded->items[0];
    $cde->flags->cde = TRUE;
    $label = "New CDE";
    $cde->label = $label;
    $response2->_embedded->items[3] = $cde;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response2->_embedded->items)
      ->shouldBeCalled();

    $environments_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'delete', 202);
    $this->clientProphecy->request('delete', "/environments/" . $cde->id)
      ->willReturn($environments_response)
      ->shouldBeCalled();

    $environments_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', 200);
    $this->clientProphecy->request('get', "/environments/" . $cde->id)
      ->willReturn($cde)
      ->shouldBeCalled();

    $notifications_response = $this->getMockResponseFromSpec( "/notifications/{notificationUuid}", 'get', '200');
    $this->clientProphecy->request('get', Argument::containingString("/notifications/"))
      ->willReturn($notifications_response)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'environmentId' => $environment_id,
      ],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'
        'n',
        // Select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );

    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("The {$cde->label} environment is being deleted", $output);

  }

  /**
   * Tests the case when no CDE available for application.
   */
  public function testNoExistingCDEEnvironment(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('There are no existing CDEs for Application');

    $this->executeCommand([],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'
        'n',
        // Select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );
  }

  /**
   * Tests the case when multiple CDE available for application.
   */
  public function testNoEnvironmentArgumentPassed(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $response = $this->getMockEnvironmentsResponse();
    foreach ($response->{'_embedded'}->items as $key => $env) {
      $env->flags->cde = TRUE;
      $response->{'_embedded'}->items[$key] = $env;
    }
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response->_embedded->items)
      ->shouldBeCalled();

    $cde = $response->_embedded->items[0];
    $environments_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'delete', 202);
    $this->clientProphecy->request('delete', "/environments/" . $cde->id)
      ->willReturn($environments_response)
      ->shouldBeCalled();

    $this->executeCommand([],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'
        'n',
        // Select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );

    $output = $this->getDisplay();

    $expected = <<<EOD
Which Continuous Delivery Environment (CDE) do you want to delete? [Dev]:
  [0] Dev
  [1] Production
  [2] Stage

EOD;
    self::assertStringContainsStringIgnoringLineEndings($expected, $output);
  }

}
