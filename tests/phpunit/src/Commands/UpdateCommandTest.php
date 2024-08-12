<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\HelloWorldCommand;
use Acquia\Cli\Tests\CommandTestBase;
use SelfUpdate\SelfUpdateManager;

class UpdateCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(HelloWorldCommand::class);
    }

    public function testSelfUpdate(): void
    {
        $this->mockSelfUpdateCommand();
        $this->executeCommand();
        self::assertEquals(0, $this->getStatusCode());
        self::assertStringContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
    }

    public function testBadResponseFailsSilently(): void
    {
        $this->mockSelfUpdateCommand(true);
        $this->executeCommand();
        self::assertEquals(0, $this->getStatusCode());
        self::assertStringNotContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function mockSelfUpdateCommand(bool $exception = false): void
    {
        $selfUpdateManager = $this->prophet->prophesize(SelfUpdateManager::class);
        if ($exception) {
            $selfUpdateManager->isUpToDate()->willThrow(new \Exception())->shouldBeCalled();
        } else {
            $selfUpdateManager->isUpToDate()
                ->willReturn(false)
                ->shouldBeCalled();
            $selfUpdateManager->getLatestReleaseFromGithub()
                ->willReturn(['tag_name' => '2.8.5'])
                ->shouldBeCalled();
        }
        $this->selfUpdateManager = $selfUpdateManager->reveal();
    }
}
