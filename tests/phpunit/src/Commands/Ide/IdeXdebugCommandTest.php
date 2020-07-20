<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeXdebugCommand;
use Acquia\Cli\Tests\Commands\Ide\Wizard\IdeRequiredTestBase;
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
    // @todo Copy fixture to a temp path where it can be messed with.
    $this->xdebugFilePath = $this->fixtureDir . '/xdebug.ini';
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
  public function testXdebugCommand(): void {
    $this->executeCommand([], []);
    $ini_file = '/home/ide/configs/php/xdebug.ini';
    $this->assertFileExists($ini_file);
    $this->assertStringContainsString('zend_extension=xdebug.so', file_get_contents($ini_file));
    $this->assertStringContainsString("Enabling xdebug in {$ini_file}...", $this->getDisplay());
  }

}
