<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

class LocalMachineHelperTest extends TestBase
{
    public function testStartBrowser(): void
    {
        putenv('DISPLAY=1');
        $localMachineHelper = $this->localMachineHelper;
        $opened = $localMachineHelper->startBrowser('https://google.com', 'cat');
        $this->assertTrue($opened, 'Failed to open browser');
        putenv('DISPLAY');
    }

    /**
     * @return bool[][]
     */
    public static function providerTestExecuteFromCmd(): array
    {
        return [
            [false, null, null],
            [false, false, false],
            [true, false, false],
        ];
    }

    /**
     * @dataProvider providerTestExecuteFromCmd()
     * @group serial
     */
    public function testExecuteFromCmd(bool $interactive, bool|null $isTty, bool|null $printOutput): void
    {
        $localMachineHelper = $this->localMachineHelper;
        $localMachineHelper->setIsTty($isTty);
        $process = $localMachineHelper->executeFromCmd('echo "hello world"', null, null, $printOutput);
        $this->assertTrue($process->isSuccessful());
        assert(is_a($this->output, BufferedOutput::class));
        $buffer = $this->output->fetch();
        if ($printOutput === false) {
            $this->assertEmpty($buffer);
        } else {
            $this->assertStringContainsString("hello world", $buffer);
        }
    }

    public function testExecuteWithCwd(): void
    {
        $this->setupFsFixture();
        $localMachineHelper = $this->localMachineHelper;
        $process = $localMachineHelper->execute([
            'ls',
            '-lash',
        ], null, $this->fixtureDir, false);
        $this->assertTrue($process->isSuccessful());
        $this->assertStringContainsString('xdebug.ini', $process->getOutput());
    }

    public function testCommandExists(): void
    {
        $localMachineHelper = $this->localMachineHelper;
        $exists = $localMachineHelper->commandExists('cat');
        $this->assertIsBool($exists);
    }

    public function testHomeDirWindowsCmd(): void
    {
        self::setEnvVars([
            'HOMEPATH' => 'something',
        ]);
        self::unsetEnvVars([
            'MSYSTEM',
            'HOME',
        ]);
        $home = LocalMachineHelper::getHomeDir();
        $this->assertEquals('something', $home);
    }

    public function testHomeDirWindowsMsys2(): void
    {
        self::setEnvVars([
            'HOMEPATH' => 'something',
            'MSYSTEM' => 'MSYS2',
        ]);
        self::unsetEnvVars(['HOME']);
        $home = LocalMachineHelper::getHomeDir();
        $this->assertEquals('something', $home);
    }

    /**
     * I don't know why, but apparently Ming is unsupported ¯\_(ツ)_/¯.
     */
    public function testHomeDirWindowsMing(): void
    {
        self::setEnvVars(['MSYSTEM' => 'MING']);
        self::unsetEnvVars(['HOME']);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not determine $HOME directory. Ensure $HOME is set in your shell.');
        LocalMachineHelper::getHomeDir();
    }

    public function testConfigDirLegacy(): void
    {
        self::setEnvVars(['HOME' => 'vfs://root']);
        $configDir = LocalMachineHelper::getConfigDir();
        $this->assertEquals('vfs://root/.acquia', $configDir);
    }

    public function testConfigDirFromXdg(): void
    {
        self::setEnvVars(['XDG_CONFIG_HOME' => 'vfs://root/.config']);
        $configDir = LocalMachineHelper::getConfigDir();
        $this->assertEquals('vfs://root/.config/acquia', $configDir);
    }

    public function testConfigDirDefault(): void
    {
        self::setEnvVars(['HOME' => 'vfs://root']);
        self::unsetEnvVars(['XDG_CONFIG_HOME']);
        unlink('vfs://root/.acquia/cloud_api.conf');
        rmdir('vfs://root/.acquia');
        $configDir = LocalMachineHelper::getConfigDir();
        $this->assertEquals('vfs://root/.config/acquia', $configDir);
    }

    public function testExecuteFromCmdEnvironmentPortableVariables(): void
    {
        $localMachineHelper = $this->localMachineHelper;
        $env = ['TEST_VAR' => 'test_value'];
        $process = $localMachineHelper->executeFromCmd('echo "${:TEST_VAR}"', null, null, false, null, $env);
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("test_value\n", str_replace("\r\n", "\n", $process->getOutput()));
    }

    public function testExecuteFromCmdDefaultPrintOutputBehavior(): void
    {
        $localMachineHelper = $this->localMachineHelper;

        // Test that executeFromCmd with default printOutput (true) captures output.
        $process = $localMachineHelper->executeFromCmd('echo "test output"');
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("test output\n", str_replace("\r\n", "\n", $process->getOutput()));

        // Test that executeFromCmd with explicit printOutput=false also works.
        $process = $localMachineHelper->executeFromCmd('echo "test output"', null, null, false);
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("test output\n", str_replace("\r\n", "\n", $process->getOutput()));
    }

    public function testExecuteDefaultPrintOutputBehavior(): void
    {
        $localMachineHelper = $this->localMachineHelper;

        // Test that execute with default printOutput (true) captures output.
        $process = $localMachineHelper->execute(['echo', 'test output']);
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("test output\n", str_replace("\r\n", "\n", $process->getOutput()));

        // Test that execute with explicit printOutput=false also works.
        $process = $localMachineHelper->execute(['echo', 'test output'], null, null, false);
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("test output\n", str_replace("\r\n", "\n", $process->getOutput()));
    }

    public function testExecuteProcessDefaultParameterValue(): void
    {
        $localMachineHelper = $this->localMachineHelper;

        // This test specifically targets the TrueValue mutation by testing the default behavior
        // The mutation changes the default value from true to false, so we need to verify
        // that the default behavior is indeed true (output is captured)
        // Test executeFromCmd with no printOutput parameter (should default to true)
        $process = $localMachineHelper->executeFromCmd('echo "default behavior test"');
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("default behavior test\n", str_replace("\r\n", "\n", $process->getOutput()));

        // Test execute with no printOutput parameter (should default to true)
        $process = $localMachineHelper->execute(['echo', 'default behavior test']);
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals("default behavior test\n", str_replace("\r\n", "\n", $process->getOutput()));
    }

    public function testExecuteProcessDefaultParameterWithReflection(): void
    {
        $localMachineHelper = $this->localMachineHelper;

        // Use reflection to test the executeProcess method directly without printOutput parameter
        // This tests the default parameter value that the TrueValue mutation targets.
        $reflection = new \ReflectionClass($localMachineHelper);
        $method = $reflection->getMethod('executeProcess');
        $method->setAccessible(true);

        // Create a process that will succeed.
        $process = new \Symfony\Component\Process\Process(['echo', 'reflection test']);

        // Call executeProcess without printOutput parameter to test the default value (true)
        // The method signature is: executeProcess(Process $process, ?callable $callback = null, ?bool $printOutput = true, ?array $env = null)
        $result = $method->invoke($localMachineHelper, $process, null);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals("reflection test\n", str_replace("\r\n", "\n", $result->getOutput()));
    }

    public function testExecuteProcessDefaultParameterCallbackBehavior(): void
    {
        // This test specifically targets the TrueValue mutation by testing the callback behavior
        // When printOutput defaults to true, a callback should be set
        // When printOutput defaults to false (mutated), no callback should be set.
        // Mock the output to capture what gets written.
        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);
        $writtenOutput = '';
        $output->method('write')->willReturnCallback(function ($buffer) use (&$writtenOutput): void {
            $writtenOutput .= $buffer;
        });

        // Create a new LocalMachineHelper with the mock output.
        $localMachineHelper = new LocalMachineHelper($this->input, $output, $this->logger);
        $localMachineHelper->setIsTty(false);

        $reflection = new \ReflectionClass($localMachineHelper);
        $method = $reflection->getMethod('executeProcess');
        $method->setAccessible(true);

        // Create a process that will succeed.
        $process = new \Symfony\Component\Process\Process(['echo', 'callback test']);

        // Call executeProcess without printOutput parameter to test the default value (true)
        // This should set a callback that writes to output.
        $result = $method->invoke($localMachineHelper, $process, null);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals("callback test\n", str_replace("\r\n", "\n", $result->getOutput()));

        // If the default is true, the callback should have written to output
        // If the default is false (mutated), no callback should be set and output should be empty.
        $this->assertNotEmpty($writtenOutput, 'Callback should have been set when printOutput defaults to true');
    }
}
