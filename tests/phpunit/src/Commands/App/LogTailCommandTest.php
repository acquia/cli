<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\LogTailCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaLogstream\LogstreamManager;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @property \Acquia\Cli\Command\App\LogTailCommand $command
 */
class LogTailCommandTest extends CommandTestBase
{
    protected LogstreamManager|ObjectProphecy $logStreamManagerProphecy;

    /**
     * @return int[]
     */
    public function providerLogTailCommand(): array
    {
        return [
        [0],
        [null],
        ];
    }

    protected function createCommand(): CommandBase
    {
        // Must initialize this here instead of in setUp() because we need the
        // prophet to be initialized first.
        $this->logStreamManagerProphecy = $this->prophet->prophesize(LogstreamManager::class);

        return new LogTailCommand(
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
            $this->logStreamManagerProphecy->reveal()
        );
    }

    /**
     * @dataProvider providerLogTailCommand
     */
    public function testLogTailCommand(?int $stream): void
    {
        $this->logStreamManagerProphecy->setColourise(true)->shouldBeCalled();
        $this->logStreamManagerProphecy->setParams(Argument::type('object'))->shouldBeCalled();
        $this->logStreamManagerProphecy->setLogTypeFilter(["bal-request"])->shouldBeCalled();
        $this->logStreamManagerProphecy->stream()->shouldBeCalled();
        $this->mockGetEnvironment();
        $this->mockLogStreamRequest();
        $this->executeCommand([], [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
        'n',
        // Select the application.
        0,
        // Would you like to link the project at ... ?
        'y',
        // Select environment.
        0,
        // Select log.
        $stream,
        ]);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('[1] Sample application 2', $output);
        $this->assertStringContainsString('Apache request', $output);
        $this->assertStringContainsString('Drupal request', $output);
    }

    public function testLogTailCommandWithEnvArg(): void
    {
        $this->mockRequest('getEnvironment', '24-a47ac10b-58cc-4372-a567-0e02b2c3d470');
        $this->mockLogStreamRequest();
        $this->executeCommand(
            ['environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470'],
            // Select log.
            [0]
        );

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Apache request', $output);
        $this->assertStringContainsString('Drupal request', $output);
    }

    public function testLogTailNode(): void
    {
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $tamper = function ($responses): void {
            foreach ($responses as $response) {
                $response->type = 'node';
            }
        };
        $this->mockRequest('getApplicationEnvironments', $application->uuid, null, null, $tamper);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No compatible environments found');
        $this->executeCommand([], [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
        'n',
        // Select the application.
        0,
        // Would you like to link the project at ... ?
        'y',
        // Select environment.
        0,
        // Select log.
        0,
        ]);
    }

    private function mockLogStreamRequest(): void
    {
        $response = $this->getMockResponseFromSpec(
            '/environments/{environmentId}/logstream',
            'get',
            '200'
        );
        $this->clientProphecy->request(
            'get',
            '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470/logstream'
        )
        ->willReturn($response)
        ->shouldBeCalled();
    }
}
