<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\Ide\IdeCreateCommand;
use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class InferApplicationTest.
 * @property LinkCommand $command
 */
class InferApplicationTest extends CommandTestBase {

  /**
   * @return \Acquia\Cli\Command\LinkCommand
   */
  protected function createCommand(): Command {
    return new LinkCommand();
  }

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->createMockGitConfigFile();
  }

  /**
   *
   */
  public function testInfer(): void {
    $this->setCommand($this->createCommand());

    $cloud_client = $this->getMockClient();
    $applications_response = $this->mockApplicationsRequest($cloud_client);
    $application_response = $this->mockApplicationRequest($cloud_client);

    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $environment_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    // The searchApplicationEnvironmentsForGitUrl() method will only look
    // for a match of the vcs url on the prod env. So, we mock a prod env.
    $environment_response2 = $environment_response;
    $environment_response2->flags->production = TRUE;
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environment_response, $environment_response2])
      ->shouldBeCalled();

    $this->application->setAcquiaCloudClient($cloud_client->reveal());

    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Would you like to link the project at ...
      'y',
    ]);

    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    
    $this->assertStringContainsString('There is no Acquia Cloud application linked to', $output);
    $this->assertStringContainsString('Searching for a matching Cloud application...', $output);
    $this->assertStringContainsString('Searching 2 applications on Acquia Cloud...', $output);
    $this->assertStringContainsString('Searching Sample application 1 for git URLs that match local git config.', $output);
    $this->assertStringContainsString('Found a matching application!', $output);
    $this->assertStringContainsString('The Cloud application with uuid a47ac10b-58cc-4372-a567-0e02b2c3d470 has been linked to the repository', $output);
  }

  /**
   *
   */
  public function testInferFailure(): void {
    $this->setCommand($this->createCommand());

    $cloud_client = $this->getMockClient();
    $applications_response = $this->mockApplicationsRequest($cloud_client);
    $application_response = $this->mockApplicationRequest($cloud_client);

    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $environment_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environment_response, $environment_response])
      ->shouldBeCalled();
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[1]->uuid}/environments")
      ->willReturn([$environment_response, $environment_response])
      ->shouldBeCalled();

    $this->application->setAcquiaCloudClient($cloud_client->reveal());

    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Please select an Acquia Cloud application:
      0,
      // Would you like to link the project at ...
      'y',
    ]);

    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('There is no Acquia Cloud application linked to', $output);
    $this->assertStringContainsString('Searching for a matching Cloud application...', $output);
    $this->assertStringContainsString('Searching 2 applications on Acquia Cloud...', $output);
    $this->assertStringContainsString('Searching Sample application 2 for git URLs that match local git config.', $output);
    $this->assertStringContainsString('Could not find a matching Cloud application.', $output);
    $this->assertStringContainsString('The Cloud application with uuid a47ac10b-58cc-4372-a567-0e02b2c3d470 has been linked to the repository', $output);
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->removeMockGitConfig();
  }

}
