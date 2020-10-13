<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestBase;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class PullCommandTestBase.
 *
 * @package Acquia\Cli\Tests\Commands\Pull
 */
abstract class PullCommandTestBase extends CommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->removeMockGitConfig();
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->removeMockGitConfig();
  }

  /**
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockEnvironmentsRequest(
    $applications_response
  ) {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $response->sshUrl = $response->ssh_url;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

}
