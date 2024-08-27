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
    private function mockSelfUpdateCommand(bool $exception = false): void
    {
        $selfUpdateManagerProphecy = $this->prophet->prophesize(SelfUpdateManager::class);
        if ($exception) {
            $selfUpdateManagerProphecy->isUpToDate()->willThrow(new Exception())->shouldBeCalled();
        } else {
            $selfUpdateManagerProphecy->isUpToDate()
                ->willReturn(false)
                ->shouldBeCalled();
            $selfUpdateManagerProphecy->getLatestReleaseFromGithub()
                ->willReturn(['tag_name' => $this->endVersion])
                ->shouldBeCalled();
        }
        $this->command->selfUpdateManager = $selfUpdateManagerProphecy->reveal();
    }
}
