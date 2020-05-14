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

  protected $targetGitConfigFixture;
  protected $sourceGitConfigFixture;

  /**
   * @return \Acquia\Cli\Command\LinkCommand
   */
  protected function createCommand(): Command {
    return new LinkCommand();
  }

  public function setUp(): void {
    parent::setUp();

    // Create mock git config file.
    $this->sourceGitConfigFixture = Path::join($this->fixtureDir, 'git_config');
    $this->targetGitConfigFixture = Path::join($this->fixtureDir, 'project', '.git', 'config');
    $this->fs->remove([$this->targetGitConfigFixture]);
    $this->fs->copy($this->sourceGitConfigFixture, $this->targetGitConfigFixture);
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
    $environment_response->flags->production = TRUE;
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environment_response])
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

    // Searching for a matching Cloud application...
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->fs->remove([$this->targetGitConfigFixture, dirname($this->targetGitConfigFixture)]);
  }

}
