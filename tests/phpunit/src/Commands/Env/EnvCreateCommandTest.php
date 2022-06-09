<?php

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\Env\EnvCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class CreateCdeCommandTest.
 *
 * @property \Acquia\Cli\Command\Env\EnvCreateCommand $command
 * @package Acquia\Cli\Tests\Commands\Env
 */
class CreateCdeCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(EnvCreateCommand::class);
  }

  /**
   * @return array
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function providerTestCreateCde(): array {
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $application_response = $applications_response->{'_embedded'}->items[0];
    return [
      [$application_response->uuid],
      [NULL],
    ];
  }

  /**
   * Tests the 'app:environment:create' command.
   *
   * @dataProvider providerTestCreateCde
   *
   * @throws \Exception
   */
  public function testCreateCde($application_uuid): void {
    $label = "New CDE";
    $applications_response = $this->mockApplicationsRequest();
    $application_response = $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);

    $response1 = $this->getMockEnvironmentsResponse();
    $response2 = $this->getMockEnvironmentsResponse();
    $cde = $response2->_embedded->items[0];
    $cde->label = $label;
    $response2->_embedded->items[3] = $cde;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response1->_embedded->items, $response2->_embedded->items)
      ->shouldBeCalled();

    $code_response = $this->getMockResponseFromSpec("/applications/{applicationUuid}/code", 'get', '200');
    $this->clientProphecy->request('get',
      "/applications/{$application_response->uuid}/code")
      ->willReturn($code_response->_embedded->items)
      ->shouldBeCalled();

    $databases_response = $this->getMockResponseFromSpec("/applications/{applicationUuid}/databases", 'get', '200');
    $this->clientProphecy->request('get',
      "/applications/{$application_response->uuid}/databases")
      ->willReturn($databases_response->_embedded->items)
      ->shouldBeCalled();

    $environments_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/environments',
      'post', 202);
    $this->clientProphecy->request('post', "/applications/{$application_response->uuid}/environments", Argument::type('array'))
      ->willReturn($environments_response->{'Adding environment'}->value)
      ->shouldBeCalled();

    $notifications_response = $this->getMockResponseFromSpec( "/notifications/{notificationUuid}", 'get', '200');
    $this->clientProphecy->request('get', Argument::containingString("/notifications/"))
      ->willReturn($notifications_response)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'label' => $label,
        'branch' => $code_response->_embedded->items[0]->name,
        'applicationUuid' => $application_uuid,
      ],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'
        'n',
        // Please select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );

    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("Your CDE URL: {$response2->_embedded->items[3]->domains[0]}", $output);

  }

}
