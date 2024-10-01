<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\TelemetryEnableCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Self\TelemetryEnableCommand $command
 */
class TelemetryEnableCommandTest extends CommandTestBase
{
    /**b
     */
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(TelemetryEnableCommand::class);
    }

    public function testTelemetryEnableCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Telemetry has been enabled.', $output);

        $settings = json_decode(file_get_contents($this->cloudConfigFilepath), true);
        $this->assertTrue($settings['send_telemetry']);
    }
}
