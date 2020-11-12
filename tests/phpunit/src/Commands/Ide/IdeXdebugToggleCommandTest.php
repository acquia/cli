<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeXdebugToggleCommand;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

/**
 * Class IdeXdebugCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdeXdebugToggleCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeXdebugToggleCommandTest extends IdeRequiredTestBase {

  /**
   * @var string
   */
  private $xdebugFilePath;

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    parent::setUp();
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
    $this->command->localMachineHelper = $local_machine_helper->reveal();
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeXdebugToggleCommand::class);
  }

  /**
   * Tests the 'ide:xdebug' command.
   * @throws \Exception
   */
  public function testXdebugCommandEnable(): void {
    $this->executeCommand([], []);
    $this->prophet->checkPredictions();
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString('zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringNotContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Enabling Xdebug PHP extension in {$this->xdebugFilePath}...", $this->getDisplay());
  }

  /**
   * Tests the 'ide:xdebug' command.
   * @throws \Exception
   */
  public function testXdebugCommandDisable(): void {
    // Modify fixture to disable xdebug.
    file_put_contents($this->xdebugFilePath, str_replace(';zend_extension=xdebug.so', 'zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath)));
    $this->executeCommand([], []);
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Disabling Xdebug PHP extension in {$this->xdebugFilePath}...", $this->getDisplay());
  }

}
