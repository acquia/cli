<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * This test class must run serially because its tests have unavoidable side effects
 * on the environment (e.g., modifying environment variables like DISPLAY) and the filesystem
 * (e.g., setting up and tearing down fixtures). Running these tests in parallel could cause
 * interference and unpredictable failures. Do not remove the @group serial annotation.
 *
 * @group serial
 */
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
}
