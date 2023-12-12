<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property LinkCommand $command
 */
class InferApplicationTest extends CommandTestBase {

  /**
   * @return \Acquia\Cli\Command\App\LinkCommand
   */
  protected function createCommand(): CommandBase {
    return $this->injectCommand(LinkCommand::class);
  }

  public function testInfer(): void {
    $this->createMockGitConfigFile();
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentResponse = $this->getMockEnvironmentResponse();
    // The searchApplicationEnvironmentsForGitUrl() method will only look
    // for a match of the vcs url on the prod env. So, we mock a prod env.
    $environmentResponse2 = $environmentResponse;
    $environmentResponse2->flags->production = TRUE;
    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environmentResponse, $environmentResponse2])
      ->shouldBeCalled();

    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Would you like to link the project at ...
      'y',
    ]);

    $output = $this->getDisplay();

    $this->assertStringContainsString('There is no Cloud Platform application linked to', $output);
    $this->assertStringContainsString('Searching for a matching Cloud application...', $output);
    $this->assertStringContainsString('Found a matching application!', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked', $output);
  }

  public function testInferFailure(): void {
    $this->createMockGitConfigFile();
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();

    $environmentResponse = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environmentResponse, $environmentResponse])
      ->shouldBeCalled();
    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[1]->uuid}/environments")
      ->willReturn([$environmentResponse, $environmentResponse])
      ->shouldBeCalled();

    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ...
      'y',
    ]);

    $output = $this->getDisplay();

    $this->assertStringContainsString('There is no Cloud Platform application linked to', $output);
    $this->assertStringContainsString('Searching for a matching Cloud application...', $output);
    $this->assertStringContainsString('Could not find a matching Cloud application.', $output);
    $this->assertStringContainsString('The Cloud application Sample application 1 has been linked', $output);
  }

  public function testInferInvalidGitConfig(): void {
    $this->expectException(AcquiaCliException::class);
    $this->executeCommand([], [
      'y',
    ]);
  }

}
