<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeXdebugToggleCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\Ide\IdeXdebugToggleCommand $command
 */
class IdeXdebugToggleCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  private string $xdebugFilePath;

  public function setUpXdebug(string $phpVersion): void {
    $this->xdebugFilePath = $this->fs->tempnam(sys_get_temp_dir(), 'acli_xdebug_ini_');
    $this->fs->copy($this->realFixtureDir . '/xdebug.ini', $this->xdebugFilePath, TRUE);
    $this->command->setXdebugIniFilepath($this->xdebugFilePath);

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper
      ->execute([
        'supervisorctl',
        'restart',
        'php-fpm',
      ], NULL, NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();
  }

  protected function createCommand(): Command {
    return $this->injectCommand(IdeXdebugToggleCommand::class);
  }

  /**
   * @return array<mixed>
   */
  public function providerTestXdebugCommandEnable(): array {
    return [
      ['7.4'],
      ['8.0'],
      ['8.1'],
    ];
  }

  /**
   * @dataProvider providerTestXdebugCommandEnable
   */
  public function testXdebugCommandEnable($phpVersion): void {
    $this->setUpXdebug($phpVersion);
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString('zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringNotContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Xdebug PHP extension enabled", $this->getDisplay());
  }

  /**
   * @dataProvider providerTestXdebugCommandEnable
   */
  public function testXdebugCommandDisable($phpVersion): void {
    $this->setUpXdebug($phpVersion);
    // Modify fixture to disable xdebug.
    file_put_contents($this->xdebugFilePath, str_replace(';zend_extension=xdebug.so', 'zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath)));
    $this->executeCommand();
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Xdebug PHP extension disabled", $this->getDisplay());
  }

}
