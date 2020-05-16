<?php

namespace Acquia\Cli\Tests;

/**
 * Class LocalMachineHelperTest.
 */
class LocalMachineHelperTest extends TestBase {

  public function setUp($output = NULL): void {
    parent::setUp();
    putenv('DISPLAY=1');
  }

  public function tearDown(): void {
    parent::tearDown();
    putenv('DISPLAY');
  }

  public function testStartBrowser(): void {
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $opened = $local_machine_helper->startBrowser('https://google.com', 'cat');
    $this->assertTrue($opened, 'Failed to open browser');
  }

}
