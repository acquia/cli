<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Dev;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Dev\DevStartCommand;
use Acquia\Cli\Command\Dev\DevStopCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class DevStartStopCommandTest extends CommandTestBase
{
    protected Client|ObjectProphecy $httpClientProphecy;

    private bool $startCommand = true;

    protected function createCommand(): CommandBase
    {
        $this->httpClientProphecy = $this->prophet->prophesize(Client::class);
        $class = $this->startCommand ? DevStartCommand::class : DevStopCommand::class;

        return new $class(
            $this->localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->acliRepoRoot,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
            $this->httpClientProphecy->reveal()
        );
    }

    public function testDevStart(): void
    {
        $dir = $this->projectDir;
        $this->fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), 'name: site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['ddev'])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $localMachineHelper->execute(['ddev', 'start', '-y'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $describe = $this->mockProcess();
        $describe->getOutput()->willReturn(json_encode(['raw' => ['primary_url' => 'https://site.ddev.site']]));
        $localMachineHelper->execute(['ddev', 'describe', '-j'], null, $dir, false)
            ->willReturn($describe->reveal())
            ->shouldBeCalled();
        $response = $this->prophet->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $this->httpClientProphecy->request('GET', 'https://site.ddev.site', [
            'http_errors' => false,
            'timeout' => 30,
            'verify' => false,
        ])
            ->willReturn($response->reveal())
            ->shouldBeCalled();

        $this->executeCommand(['--dir' => $dir], [], OutputInterface::VERBOSITY_NORMAL);

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Your local site is running: https://site.ddev.site', $this->getDisplay());
        $this->assertStringContainsString('ddev drush uli', $this->getDisplay());
        $this->assertStringContainsString('acli dev:stop', $this->getDisplay());
        $this->assertStringNotContainsString('did not respond as expected', $this->getDisplay());
    }

    /**
     * An unreachable site (request exception) warns rather than crashing.
     */
    public function testDevStartSiteUnreachable(): void
    {
        $dir = $this->projectDir;
        $this->fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), 'name: site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['ddev'])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $localMachineHelper->execute(['ddev', 'start', '-y'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $describe = $this->mockProcess();
        $describe->getOutput()->willReturn(json_encode(['raw' => ['primary_url' => 'https://site.ddev.site']]));
        $localMachineHelper->execute(['ddev', 'describe', '-j'], null, $dir, false)
            ->willReturn($describe->reveal())
            ->shouldBeCalled();
        $this->httpClientProphecy->request('GET', 'https://site.ddev.site', Argument::type('array'))
            ->willThrow(new \Exception('Connection refused'))
            ->shouldBeCalled();

        $this->executeCommand(['--dir' => $dir], [], OutputInterface::VERBOSITY_NORMAL);

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('did not respond as expected at https://site.ddev.site (HTTP no response)', $this->getDisplay());
    }

    /**
     * When `ddev describe` gives no usable JSON, the URL falls back to the
     * conventional <project>.ddev.site name; an error status from the site
     * produces a warning with next steps rather than a claimed success.
     */
    public function testDevStartUrlFallbackAndUnhealthySite(): void
    {
        $dir = $this->projectDir;
        $this->fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), 'name: site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['ddev'])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $localMachineHelper->execute(['ddev', 'start', '-y'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $describe = $this->mockProcess();
        // Valid JSON without the expected key still falls back.
        $describe->getOutput()->willReturn(json_encode(['raw' => ['status' => 'running']]));
        $localMachineHelper->execute(['ddev', 'describe', '-j'], null, $dir, false)
            ->willReturn($describe->reveal())
            ->shouldBeCalled();
        $fallbackUrl = 'https://' . basename($dir) . '.ddev.site';
        $response = $this->prophet->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(400);
        $this->httpClientProphecy->request('GET', $fallbackUrl, [
            'http_errors' => false,
            'timeout' => 30,
            'verify' => false,
        ])
            ->willReturn($response->reveal())
            ->shouldBeCalled();

        $this->executeCommand(['--dir' => $dir], [], OutputInterface::VERBOSITY_NORMAL);

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString("did not respond as expected at $fallbackUrl (HTTP 400)", $this->getDisplay());
    }

    public function testDevStartWithoutProject(): void
    {
        $this->mockLocalMachineHelper();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No local environment found in ' . $this->projectDir . '. Run this command from your project directory, or run `acli dev:init` to create one.');
        $this->executeCommand(['--dir' => $this->projectDir], [], OutputInterface::VERBOSITY_NORMAL);
    }

    public function testDevStop(): void
    {
        $this->startCommand = false;
        $this->command = $this->createCommand();
        $dir = $this->projectDir;
        $this->fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), 'name: site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['ddev'])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $localMachineHelper->execute(['ddev', 'stop'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->executeCommand(['--dir' => $dir], [], OutputInterface::VERBOSITY_NORMAL);

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Local environment stopped. Run `acli dev:start` to bring it back.', $this->getDisplay());
    }
}
