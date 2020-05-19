<?php

namespace Acquia\Cli\Tests;

/**
 * Class LocalMachineHelperTest.
 */
class LocalMachineHelperTest extends TestBase {

  public function providerTestStartBrowser() {
    return [
      ['cat'],
      [NULL],
    ];
  }

  /**
   * @dataProvider providerTestStartBrowser
   */
  public function testStartBrowser($browser): void {
    putenv('DISPLAY=1');
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $opened = $local_machine_helper->startBrowser('https://google.com', $browser);
    $this->assertTrue($opened, 'Failed to open browser');
    putenv('DISPLAY');
  }

  /**
   * @return array
   */
  public function providerTestExecuteFromCmd(): array {
    return [
      [FALSE, NULL, NULL],
      [FALSE, FALSE, FALSE],
      [TRUE, FALSE, FALSE],
      [TRUE, TRUE, FALSE],
    ];
  }

  /**
   * @dataProvider providerTestExecuteFromCmd()
   *
   * @param $interactive
   * @param $is_tty
   * @param $print_output
   */
  public function testExecuteFromCmd($interactive, $is_tty, $print_output): void {
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $local_machine_helper->setIsTty($is_tty);
    $this->input->setInteractive($interactive);
    $process = $local_machine_helper->executeFromCmd('echo "hello world"', NULL, NULL, $print_output);
    $this->assertTrue($process->isSuccessful());
  }

  public function testExecuteWithCwd(): void {
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $process = $local_machine_helper->execute(['ls', '-lash'], NULL, $this->projectFixtureDir, FALSE);
    $this->assertTrue($process->isSuccessful());
    $this->assertStringContainsString('docroot', $process->getOutput());
  }

  public function testCommandExists(): void {
    $local_machine_helper = $this->application->getLocalMachineHelper();
    $exists = $local_machine_helper->commandExists('cat');
    $this->assertIsBool($exists);
  }

}
