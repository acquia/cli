<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeXdebugCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeXdebugCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdeXdebugCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeXdebugCommandTest extends IdeRequiredTestBase {

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
    $this->fs->copy($this->fixtureDir . '/xdebug.ini', $this->xdebugFilePath);
    $this->command->setXdebugIniFilepath($this->xdebugFilePath);
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeXdebugCommand::class);
  }

  /**
   * Tests the 'ide:xdebug' command.
   * @throws \Exception
   */
  public function testXdebugCommandEnable(): void {
    $this->executeCommand([], []);
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString('zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringNotContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Enabling xdebug in {$this->xdebugFilePath}...", $this->getDisplay());
  }

  /**
   * Tests the 'ide:xdebug' command.
   * @throws \Exception
   */
  public function testXdebugCommandDisable(): void {
    $this->executeCommand([], []);
    $this->assertFileExists($this->xdebugFilePath);
    $this->assertStringContainsString(';zend_extension=xdebug.so', file_get_contents($this->xdebugFilePath));
    $this->assertStringContainsString("Disabling xdebug in {$this->xdebugFilePath}...", $this->getDisplay());
  }

}
