<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;

/**
 * @package Acquia\Cli\Tests\Application
 */
class KernelTest extends ApplicationTestBase {

  /**
   * @throws \Exception
   * @group serial
   */
  public function testRun(): void {
    $this->setInput(['list']);
    $buffer = $this->runApp();
    $this->assertStringContainsString('Available commands:', $buffer);
  }

}
