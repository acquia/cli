<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Process\Process;

/**
 * Class SshCommandTestBase.
 *
 * @package Acquia\Cli\Tests\Remote
 */
abstract class SshCommandTestBase extends CommandTestBase {

  /**
   * @param $cloud_client
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockForGetEnvironmentFromAliasArg($cloud_client): void {
    $applications_response = $this->mockApplicationsRequest($cloud_client);
    $environments_response = $this->mockEnvironmentsRequest($cloud_client,
      $applications_response);
    $cloud_client->clearQuery()->shouldBeCalled();
    $cloud_client->addQuery('filter', 'hosting=@*devcloud2')->shouldBeCalled();
    $this->application->setAcquiaCloudClient($cloud_client->reveal());
  }

  /**
   * @return array
   */
  protected function mockForExecuteCommand(): array {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE)->shouldBeCalled();
    $local_machine_helper->setIsTty(TRUE)->shouldBeCalled();
    return [$process, $local_machine_helper];
  }

}
