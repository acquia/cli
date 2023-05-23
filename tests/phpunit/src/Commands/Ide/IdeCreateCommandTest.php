<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;

/**
 * @property IdeCreateCommand $command
 */
class IdeCreateCommandTest extends CommandTestBase {

  /**
   * Tests the 'ide:create' command.
   */
  public function testCreate(): void {
    $applicationsResponse = $this->mockRequest('getApplications');
    $applicationUuid = $applicationsResponse[self::$INPUT_DEFAULT_CHOICE]->uuid;
    $this->mockRequest('getApplicationByUuid', $applicationUuid);
    $this->mockRequest('getAccount');
    $ideResponse = $this->mockRequest(
      'postApplicationsIde',
      $applicationUuid,
      ['json' => ['label' => 'Example IDE']],
      'IDE created'
    );
    $cloudApiIdeUrl = $ideResponse->_links->self->href;
    $urlParts = explode('/', $cloudApiIdeUrl);
    $ideUuid = end($urlParts);
    $this->mockRequest('getIde', $ideUuid);

    /** @var \Prophecy\Prophecy\ObjectProphecy|\GuzzleHttp\Psr7\Response $guzzleResponse */
    $guzzleResponse = $this->prophet->prophesize(Response::class);
    $guzzleResponse->getStatusCode()->willReturn(200);
    $guzzleClient = $this->prophet->prophesize(Client::class);
    $guzzleClient->request('GET', '/health')->willReturn($guzzleResponse->reveal())->shouldBeCalled();
    $this->command->setClient($guzzleClient->reveal());

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      // Would you like to link the project at ... ?
      'n',
      0,
      self::$INPUT_DEFAULT_CHOICE,
      // Enter a label for your Cloud IDE:
      'Example IDE',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('  [0] Sample application 1', $output);
    $this->assertStringContainsString('  [1] Sample application 2', $output);
    $this->assertStringContainsString("Enter the label for the IDE (option --label) [Jane Doe's IDE]:", $output);
    $this->assertStringContainsString('Your IDE is ready!', $output);
    $this->assertStringContainsString('Your IDE URL: https://215824ff-272a-4a8c-9027-df32ed1d68a9.ides.acquia.com', $output);
    $this->assertStringContainsString('Your Drupal Site URL: https://ide-215824ff-272a-4a8c-9027-df32ed1d68a9.prod.acquia-sites.com', $output);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\IdeCreateCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeCreateCommand::class);
  }

}
