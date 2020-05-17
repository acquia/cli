<?php

namespace Acquia\Cli\Tests;

/**
 * Class LocalMachineHelperTest.
 */
class LocalMachineHelperTest extends TestBase {

  public function testStartBrowser(): void {
    putenv('DISPLAY=1');
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $opened = $local_machine_helper->startBrowser('https://google.com', 'cat');
    $this->assertTrue($opened, 'Failed to open browser');
    putenv('DISPLAY');
  }

  public function testExecuteFromCmd(): void {
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $process = $local_machine_helper->executeFromCmd('echo "hello world"', NULL, NULL, FALSE);
    $this->assertTrue($process->isSuccessful());
  }

  public function testExecuteWithCwd(): void {
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $process = $local_machine_helper->execute(['ls', '-lash'], NULL, $this->projectFixtureDir, FALSE);
    $this->assertTrue($process->isSuccessful());
    $this->assertStringContainsString('docroot', $process->getOutput());
  }

}
