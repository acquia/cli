<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Env\EnvDeleteCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Env\EnvDeleteCommand $command
 */
class EnvDeleteCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(EnvDeleteCommand::class);
  }

  /**
   * @return array<mixed>
   */
  public function providerTestDeleteCde(): array {
    $environmentResponse = $this->getMockEnvironmentsResponse();
    $environment = $environmentResponse->_embedded->items[0];
    return [
      [$environment->id],
      [NULL],
    ];
  }

  /**
   * @dataProvider providerTestDeleteCde
   * @group brokenProphecy
   */
  public function testDeleteCde(mixed $environmentId): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applicationsResponse);

    $this->getMockEnvironmentsResponse();
    $response2 = $this->getMockEnvironmentsResponse();
    $cde = $response2->_embedded->items[0];
    $cde->flags->cde = TRUE;
    $label = "New CDE";
    $cde->label = $label;
    $response2->_embedded->items[3] = $cde;
    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response2->_embedded->items)
      ->shouldBeCalled();

    $environmentsResponse = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'delete', 202);
    $this->clientProphecy->request('delete', "/environments/" . $cde->id)
      ->willReturn($environmentsResponse)
      ->shouldBeCalled();

    $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', 200);
    $this->clientProphecy->request('get', "/environments/" . $cde->id)
      ->willReturn($cde)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'environmentId' => $environmentId,
      ],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'.
        'n',
        // Select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("The $cde->label environment is being deleted", $output);

  }

  /**
   * @group brokenProphecy
   */
  public function testNoExistingCDEEnvironment(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applicationsResponse);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('There are no existing CDEs for Application');

    $this->executeCommand([],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'.
        'n',
        // Select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );
  }

  /**
   * @group brokenProphecy
   */
  public function testNoEnvironmentArgumentPassed(): void {
    $applications = $this->mockRequest('getApplications');
    $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
    $environments = $this->mockRequest('getApplicationEnvironments', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
    foreach ($environments as $environment) {
      $environment->flags->cde = TRUE;
    }
    $this->clientProphecy->request('get',
      "/applications/{$application->uuid}/environments")
      ->willReturn($environments)
      ->shouldBeCalled();

    $cde = $environments[0];
    $environmentsResponse = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'delete', 202);
    $this->clientProphecy->request('delete', "/environments/" . $cde->id)
      ->willReturn($environmentsResponse)
      ->shouldBeCalled();

    $this->executeCommand([],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'.
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
