<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\EnvironmentMirrorCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;

/**
 * Class EnvironmentMirrorCommandTest.
 *
 * @property \Acquia\Cli\Command\App\EnvironmentMirrorCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class EnvironmentMirrorCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(EnvironmentMirrorCommand::class);
  }

  /**
   * Tests the 'app:environment-mirror' command.
   *
   * @throws \Exception
   */
  public function testEnvironmentMirror(): void {
    $environment_response = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get',
      "/environments/" . $environment_response->id)
      ->willReturn($environment_response)
      ->shouldBeCalled();

    $code_switch_response = $this->getMockResponseFromSpec("/environments/{environmentId}/code/actions/switch", 'post', '202');
    $response = $code_switch_response->{'Switching code'}->value;
    $response->links = $response->{'_links'};
    $this->clientProphecy->request('post',
      "/environments/{$environment_response->id}/code/actions/switch", [
        'form_params' => [
          'branch' => $environment_response->vcs->path,
        ],
      ])
      ->willReturn($response)
      ->shouldBeCalled();

    $databases_response = $this->getMockResponseFromSpec("/environments/{environmentId}/databases", 'get', '200');
    $this->clientProphecy->request('get',
      "/environments/{$environment_response->id}/databases")
      ->willReturn($databases_response->_embedded->items)
      ->shouldBeCalled();

    $db_copy_response = $this->getMockResponseFromSpec("/environments/{environmentId}/databases", 'post', '202');
    $response = $db_copy_response->{'Database being copied'}->value;
    $response->links = $response->{'_links'};
    $this->clientProphecy->request('post', "/environments/{$environment_response->id}/databases", [
      'json' => [
        'name' => $databases_response->_embedded->items[0]->name,
        'source' => $environment_response->id,
      ],
    ])
      ->willReturn($response)
      ->shouldBeCalled();

    $files_copy_response = $this->getMockResponseFromSpec("/environments/{environmentId}/files", 'post', '202');
    $response = $files_copy_response->{'Files queued for copying'}->value;
    $response->links = $response->{'_links'};
    $this->clientProphecy->request('post', "/environments/{$environment_response->id}/files", [
      'json' => [
        'source' => $environment_response->id,
      ],
    ])
      ->willReturn($response)
      ->shouldBeCalled();

    $environment_update_response = $this->getMockResponseFromSpec("/environments/{environmentId}", 'put', '202');
    $this->clientProphecy->request('put', "/environments/{$environment_response->id}", Argument::type('array'))
      ->willReturn($environment_update_response)
      ->shouldBeCalled();

    $notifications_response = $this->getMockResponseFromSpec( "/notifications/{notificationUuid}", 'get', '200');
    $this->clientProphecy->request('get', Argument::containingString("/notifications/"))
      ->willReturn($notifications_response)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'source-environment' => $environment_response->id,
        'destination-environment' => $environment_response->id,
      ],
      [
        // Are you sure that you want to overwrite everything ...
        'y'
      ]
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('Are you sure that you want to overwrite everything on Dev (dev) and replace it with source data from Dev (dev)', $output);
    $this->assertStringContainsString('[OK] Done! Dev now matches Dev ', $output);
  }

}