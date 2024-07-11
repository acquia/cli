<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Env\EnvCopyCronCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use Prophecy\Argument;

/**
 * @property \Acquia\Cli\Command\Env\EnvCopyCronCommand $command
 */
class EnvCopyCronCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(EnvCopyCronCommand::class);
    }

    public function testCopyCronTasksCommandTest(): void
    {
        $environmentsResponse = $this->getMockEnvironmentsResponse();
        $sourceCronsListResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'get', '200');
        $this->clientProphecy->request(
            'get',
            '/environments/' . $environmentsResponse->{'_embedded'}->items[0]->id . '/crons'
        )
            ->willReturn($sourceCronsListResponse->{'_embedded'}->items)
            ->shouldBeCalled();

        $createCronResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'post', '202');
        $this->clientProphecy->request(
            'post',
            '/environments/' . $environmentsResponse->{'_embedded'}->items[2]->id . '/crons',
            Argument::type('array')
        )
            ->willReturn($createCronResponse->{'Adding cron'}->value)
            ->shouldBeCalled();

        $source = '24-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $dest = '32-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $this->executeCommand(
            [
                'dest_env' => $dest,
                'source_env' => $source,
            ],
            [
                'y',
            ]
        );

        $output = $this->getDisplay();
        $this->assertStringContainsString('Are you sure you\'d like to copy the cron jobs from ' . $source . ' to ' . $dest . '? (yes/no) [yes]:', $output);
        $this->assertStringContainsString('Copying the cron task "Clear drush caches" from ', $output);
        $this->assertStringContainsString($source . ' to', $output);
        $this->assertStringContainsString($dest, $output);
        $this->assertStringContainsString('[OK] Cron task copy is completed.', $output);
    }

    public function testCopyCronTasksCommandTestFail(): void
    {
        $this->executeCommand([
            'dest_env' => 'app.test',
            'source_env' => 'app.test',
        ],);
        $output = $this->getDisplay();
        $this->assertStringContainsString('The source and destination environments can not be same', $output);
    }

    /**
     * Tests for no cron job available on source environment to copy.
     */
    public function testNoCronJobOnSource(): void
    {
        $environmentsResponse = $this->getMockEnvironmentsResponse();
        $this->clientProphecy->request(
            'get',
            '/environments/' . $environmentsResponse->{'_embedded'}->items[0]->id . '/crons'
        )
            ->willReturn([])
            ->shouldBeCalled();

        $source = '24-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $dest = '32-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $this->executeCommand(
            [
                'dest_env' => $dest,
                'source_env' => $source,
            ],
            [
                'y',
            ]
        );

        $output = $this->getDisplay();
        $this->assertStringContainsString('There are no cron jobs in the source environment for copying.', $output);
    }

    /**
     * Tests for exception during the cron job copy.
     */
    public function testExceptionOnCronJobCopy(): void
    {
        $environmentsResponse = $this->getMockEnvironmentsResponse();
        $sourceCronsListResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'get', '200');
        $this->clientProphecy->request(
            'get',
            '/environments/' . $environmentsResponse->{'_embedded'}->items[0]->id . '/crons'
        )
            ->willReturn($sourceCronsListResponse->{'_embedded'}->items)
            ->shouldBeCalled();

        $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'post', '202');
        $this->clientProphecy->request(
            'post',
            '/environments/' . $environmentsResponse->{'_embedded'}->items[2]->id . '/crons',
            Argument::type('array')
        )
            ->willThrow(Exception::class);

        $source = '24-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $dest = '32-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $this->executeCommand(
            [
                'dest_env' => $dest,
                'source_env' => $source,
            ],
            [
                'y',
            ]
        );

        $output = $this->getDisplay();
        $this->assertStringContainsString('There was some error while copying the cron task', $output);
    }
}
