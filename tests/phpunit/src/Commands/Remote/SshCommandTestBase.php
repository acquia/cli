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
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockForGetEnvironmentFromAliasArg(): void {
    $applications_response = $this->mockApplicationsRequest(1);
    $this->mockEnvironmentsRequest($applications_response);
    $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')->shouldBeCalled();
    $this->mockAccountRequest();
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
    return [$process, $local_machine_helper];
  }

}
