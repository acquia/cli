<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class InferApplicationTest.
 * @property LinkCommand $command
 */
class InferApplicationTest extends CommandTestBase {

  /**
   * @return \Acquia\Cli\Command\App\LinkCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(LinkCommand::class);
  }

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->createMockGitConfigFile();
  }

  /**
   *
   */
  public function testInfer(): void {

    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environment_response = $this->getMockEnvironmentResponse();
    // The searchApplicationEnvironmentsForGitUrl() method will only look
    // for a match of the vcs url on the prod env. So, we mock a prod env.
    $environment_response2 = $environment_response;
    $environment_response2->flags->production = TRUE;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environment_response, $environment_response2])
      ->shouldBeCalled();

    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Would you like to link the project at ...
      'y',
    ]);

    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('There is no Cloud Platform application linked to', $output);
    $this->assertStringContainsString('Searching for a matching Cloud application...', $output);
    $this->assertStringContainsString('Found a matching application!', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked', $output);
  }

  /**
   *
   */
  public function testInferFailure(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();

    $environment_response = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environment_response, $environment_response])
      ->shouldBeCalled();
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[1]->uuid}/environments")
      ->willReturn([$environment_response, $environment_response])
      ->shouldBeCalled();

    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ...
      'y',
    ]);

    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('There is no Cloud Platform application linked to', $output);
    $this->assertStringContainsString('Searching for a matching Cloud application...', $output);
    $this->assertStringContainsString('Could not find a matching Cloud application.', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked', $output);
  }

}
