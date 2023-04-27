<?php

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\Env\EnvMirrorCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Env\EnvMirrorCommand $command
 */
class EnvMirrorCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(EnvMirrorCommand::class);
  }

  /**
   * Tests the 'app:environment-mirror' command.
   */
  public function testEnvironmentMirror(): void {
    $environment_response = $this->mockGetEnvironments();
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

    $this->mockNotificationResponse('bfd9a39b-a85e-4de3-8a70-042d1c7e607a');
    $this->mockNotificationResponse('a49eeebb-0929-444a-972c-07b94ce93ab9');
    $this->mockNotificationResponse('d53fccec-5c1b-4ad4-b431-5cd39ad2b453');
    $this->mockNotificationResponse('737a97a4-4c02-47e4-9924-d008de1aa7e5');

    $this->executeCommand(
      [
        'destination-environment' => $environment_response->id,
        'source-environment' => $environment_response->id,
      ],
      [
        // Are you sure that you want to overwrite everything ...
        'y',
      ]
    );

    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Are you sure that you want to overwrite everything on Dev (dev) and replace it with source data from Dev (dev)', $output);
    $this->assertStringContainsString("Switching to {$environment_response->vcs->path}", $output);
    $this->assertStringContainsString("Copying {$databases_response->_embedded->items[0]->name}", $output);
    $this->assertStringContainsString("Copying PHP version, acpu memory limit, etc.", $output);
    $this->assertStringContainsString("[OK] Done! {$environment_response->label} now matches {$environment_response->label}", $output);
  }

}
