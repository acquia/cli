<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\HelloWorldCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use SelfUpdate\SelfUpdateManager;

class UpdateCommandTest extends CommandTestBase
{
    private string $startVersion = '1.0.0';
    private string $endVersion = '2.8.5';
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(HelloWorldCommand::class);
    }

    public function testSelfUpdate(): void
    {
        $this->application->setVersion($this->startVersion);
        $this->mockSelfUpdateCommand();
        $this->executeCommand();
        self::assertEquals(0, $this->getStatusCode());
        self::assertStringContainsString("Acquia CLI $this->endVersion is available", $this->getDisplay());
    }

    public function testUpdateCheckIsCached(): void
    {
        $this->application->setVersion($this->startVersion);
        $this->mockSelfUpdateCommand(false, 1);
        $this->executeCommand();
        self::assertStringContainsString("Acquia CLI $this->endVersion is available", $this->getDisplay());
        // The second run must use the cached result rather than hitting the
        // GitHub API again; shouldBeCalledTimes(1) on the mock enforces it.
        $this->executeCommand();
        self::assertEquals(0, $this->getStatusCode());
        self::assertStringContainsString("Acquia CLI $this->endVersion is available", $this->getDisplay());
    }

    public function testNoUpdateMessageWhenUpToDate(): void
    {
        CommandBase::getUpdateCheckCache()->clear();
        $this->application->setVersion($this->startVersion);
        // The default mock reports the CLI as up to date; no upgrade message
        // should be shown (and checkForNewVersion() must return false, not true).
        $this->executeCommand();
        self::assertEquals(0, $this->getStatusCode());
        self::assertStringNotContainsString('is available', $this->getDisplay());
    }

    public function testBadResponseFailsSilently(): void
    {
        $this->application->setVersion($this->startVersion);
        $this->mockSelfUpdateCommand(true);
        $this->executeCommand();
        self::assertEquals(0, $this->getStatusCode());
        self::assertStringNotContainsString("Acquia CLI $this->endVersion is available", $this->getDisplay());
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function mockSelfUpdateCommand(bool $exception = false, ?int $expectedChecks = null): void
    {
        CommandBase::getUpdateCheckCache()->clear();
        $selfUpdateManagerProphecy = $this->prophet->prophesize(SelfUpdateManager::class);
        if ($exception) {
            $selfUpdateManagerProphecy->isUpToDate()->willThrow(new Exception())->shouldBeCalled();
        } else {
            $isUpToDate = $selfUpdateManagerProphecy->isUpToDate()
                ->willReturn(false);
            if ($expectedChecks === null) {
                $isUpToDate->shouldBeCalled();
            } else {
                $isUpToDate->shouldBeCalledTimes($expectedChecks);
            }
            $selfUpdateManagerProphecy->getLatestReleaseFromGithub()
                ->willReturn(['tag_name' => $this->endVersion])
                ->shouldBeCalled();
        }
        $this->command->selfUpdateManager = $selfUpdateManagerProphecy->reveal();
    }
}
