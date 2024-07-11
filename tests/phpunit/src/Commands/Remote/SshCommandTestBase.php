<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Process\Process;

abstract class SshCommandTestBase extends CommandTestBase
{
    protected function mockForGetEnvironmentFromAliasArg(): void
    {
        $applicationsResponse = $this->mockApplicationsRequest(1);
        $this->mockEnvironmentsRequest($applicationsResponse);
        $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')
            ->shouldBeCalled();
        $this->mockRequest('getAccount');
    }

    /**
     * @return array<mixed>
     */
    protected function mockForExecuteCommand(): array
    {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);
        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->useTty()->willReturn(false)->shouldBeCalled();
        return [$process, $localMachineHelper];
    }
}
