<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\DrushCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Argument\Token\TypeToken;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

/**
 * Class DrushCommandTest.
 *
 * @property DrushCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class DrushCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new DrushCommand();
  }

  /**
   * Tests the 'remote:drush' commands.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRemoteDrushCommand(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();

    // Request for applications.
    $applications_response = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $cloud_client->request('get', '/applications')->willReturn($applications_response->{'_embedded'}->items)->shouldBeCalled();

    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}', 'get', '200');
    $cloud_client->request('get', "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")->willReturn([$response])->shouldBeCalled();
    $this->application->setAcquiaCloudClient($cloud_client->reveal());

    $ssh_command = [
      0 => 'ssh',
      1 => '-T',
      2 => 'site.dev@server-123.hosted.hosting.acquia.com',
      3 => '-o StrictHostKeyChecking=no',
      4 => '-o AddressFamily inet',
      5 => 'cd /var/www/html/devcloud2.dev/docroot; ',
      6 => 'drush',
      'drush_command' => 'status',
    ];
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);
    $local_machine_helper
      ->execute($ssh_command, Argument::type('callable'))
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->application->setLocalMachineHelper($local_machine_helper->reveal());

    $args = [
      'alias' => 'devcloud2.dev',
      'drush_command' => 'status',
      '-vvv' => '',
    ];
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
