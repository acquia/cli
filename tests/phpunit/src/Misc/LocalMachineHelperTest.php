<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

class LocalMachineHelperTest extends TestBase {

  public function testStartBrowser(): void {
    putenv('DISPLAY=1');
    $localMachineHelper = $this->localMachineHelper;
    $opened = $localMachineHelper->startBrowser('https://google.com', 'cat');
    $this->assertTrue($opened, 'Failed to open browser');
    putenv('DISPLAY');
  }

  /**
   * @return bool[][]
   */
  public function providerTestExecuteFromCmd(): array {
    return [
      [FALSE, NULL, NULL],
      [FALSE, FALSE, FALSE],
      [TRUE, FALSE, FALSE],
    ];
  }

  /**
   * @dataProvider providerTestExecuteFromCmd()
   */
  public function testExecuteFromCmd(bool $interactive, bool|NULL $isTty, bool|NULL $printOutput): void {
    $localMachineHelper = $this->localMachineHelper;
    $localMachineHelper->setIsTty($isTty);
    $this->input->setInteractive($interactive);
    $process = $localMachineHelper->executeFromCmd('echo "hello world"', NULL, NULL, $printOutput);
    $this->assertTrue($process->isSuccessful());
    assert(is_a($this->output, BufferedOutput::class));
    $buffer = $this->output->fetch();
    if ($printOutput === FALSE) {
      $this->assertEmpty($buffer);
    }
    else {
      $this->assertStringContainsString("hello world", $buffer);
    }
  }

  public function testExecuteWithCwd(): void {
    $this->setupFsFixture();
    $localMachineHelper = $this->localMachineHelper;
    $process = $localMachineHelper->execute(['ls', '-lash'], NULL, $this->fixtureDir, FALSE);
    $this->assertTrue($process->isSuccessful());
    $this->assertStringContainsString('xdebug.ini', $process->getOutput());
  }

  public function testCommandExists(): void {
    $localMachineHelper = $this->localMachineHelper;
    $exists = $localMachineHelper->commandExists('cat');
    $this->assertIsBool($exists);
  }

  public function testHomeDirWindowsCmd(): void {
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

  public function testHomeDirWindowsMsys2(): void {
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
  public function testHomeDirWindowsMing(): void {
    self::setEnvVars(['MSYSTEM' => 'MING']);
    self::unsetEnvVars(['HOME']);
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Could not determine $HOME directory. Ensure $HOME is set in your shell.');
    LocalMachineHelper::getHomeDir();
  }

}
