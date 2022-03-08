<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeXdebugToggleCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

/**
 * Class IdeXdebugCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdeXdebugToggleCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeXdebugToggleCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  /**
   * @var string
   */
  private $xdebugFilePath;

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   */
  public function setUpXdebug($php_version): void {
    self::setCloudIdeEnvVars();
    $this->xdebugFilePath = $this->fs->tempnam(sys_get_temp_dir(), 'acli_xdebug_ini_');
    $this->fs->copy($this->fixtureDir . '/xdebug.ini', $this->xdebugFilePath, TRUE);
    $this->command->setXdebugIniFilepath($this->xdebugFilePath);

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper
      ->execute([
        'supervisorctl',
        'restart',
        'php-fpm',
      ], NULL, NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $local_machine_helper->readFile('/home/ide/configs/php/.version')->willReturn($php_version);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeXdebugToggleCommand::class);
  }

  public function providerTestXdebugCommandEnable() {
    return [
      ['7.4'],
      ['8.0'],
      ['8.1'],
    ];
  }

  /**
   * Tests the 'ide:xdebug' command.
   *
   * @dataProvider providerTestXdebugCommandEnable
   *
   * @throws \Exception
   */
  public function testXdebugCommandEnable($php_version): void {
    $this->setUpXdebug($php_version);
    $this->executeCommand([], []);
    $this->prophet->checkPredictions();
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString('zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringNotContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Xdebug PHP extension enabled", $this->getDisplay());
  }

  /**
   * Tests the 'ide:xdebug' command.
   *
   * @dataProvider providerTestXdebugCommandEnable
   * @throws \Exception
   */
  public function testXdebugCommandDisable($php_version): void {
    $this->setUpXdebug($php_version);
    // Modify fixture to disable xdebug.
    file_put_contents($this->xdebugFilePath, str_replace(';zend_extension=xdebug.so', 'zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath)));
    $this->executeCommand([], []);
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Xdebug PHP extension disabled", $this->getDisplay());
  }

}
