<?php

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\Env\EnvCreateCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Env\EnvCreateCommand $command
 */
class EnvCreateCommandTest extends CommandTestBase {

  private static string $validLabel = 'New CDE';

  private function setupCdeTest(string $label): string {
    $applications_response = $this->mockApplicationsRequest();
    $application_response = $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);

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
      "/applications/$application_response->uuid/code")
      ->willReturn($code_response->_embedded->items)
      ->shouldBeCalled();

    $databases_response = $this->getMockResponseFromSpec("/applications/{applicationUuid}/databases", 'get', '200');
    $this->clientProphecy->request('get',
      "/applications/$application_response->uuid/databases")
      ->willReturn($databases_response->_embedded->items)
      ->shouldBeCalled();

    $environments_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/environments',
      'post', 202);
    $this->clientProphecy->request('post', "/applications/$application_response->uuid/environments", Argument::type('array'))
      ->willReturn($environments_response->{'Adding environment'}->value)
      ->shouldBeCalled();

    $notifications_response = $this->getMockResponseFromSpec("/notifications/{notificationUuid}", 'get', '200');
    $this->clientProphecy->request('get', Argument::containingString("/notifications/"))
      ->willReturn($notifications_response)
      ->shouldBeCalled();
    return $response2->_embedded->items[3]->domains[0];
  }

  private function getBranch(): string {
    $code_response = $this->getMockResponseFromSpec("/applications/{applicationUuid}/code", 'get', '200');
    return $code_response->_embedded->items[0]->name;
  }

  private function getApplication(): string {
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    return $applications_response->{'_embedded'}->items[0]->uuid;
  }

  protected function createCommand(): Command {
    return $this->injectCommand(EnvCreateCommand::class);
  }

  /**
   * @return array
   */
  public function providerTestCreateCde(): array {
    $application = $this->getApplication();
    $branch = $this->getBranch();
    return [
      // No args, only interactive input.
      [[NULL, NULL], ['n', 0, 0]],
      // Branch as arg.
      [[$branch, NULL], ['n', 0]],
      // Branch and app id as args.
      [[$branch, $application], []],
    ];
  }

  /**
   * Tests the 'app:environment:create' command.
   *
   * @dataProvider providerTestCreateCde
   */
  public function testCreateCde($args, $input): void {
    $domain = $this->setupCdeTest(self::$validLabel);

    $this->executeCommand(
      [
        'applicationUuid' => $args[1],
        'branch' => $args[0],
        'label' => self::$validLabel,
      ],
      $input
    );

    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("Your CDE URL: $domain", $output);
  }

  public function testCreateCdeNonUniqueLabel(): void {
    $label = 'Dev';
    $this->setupCdeTest($label);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('An environment named Dev already exists.');
    $this->executeCommand(
      [
        'applicationUuid' => $this->getApplication(),
        'branch' => $this->getBranch(),
        'label' => $label,
      ]
    );
  }

  public function testCreateCdeInvalidTag(): void {
    $this->setupCdeTest(self::$validLabel);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('There is no branch or tag with the name bogus on the remote VCS.');
    $this->executeCommand(
      [
        'applicationUuid' => $this->getApplication(),
        'branch' => 'bogus',
        'label' => self::$validLabel,
      ]
    );
  }

}
