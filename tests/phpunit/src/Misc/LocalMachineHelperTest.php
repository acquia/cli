<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Tests\TestBase;

/**
 * Class LocalMachineHelperTest.
 */
class LocalMachineHelperTest extends TestBase {

  public function testStartBrowser(): void {
    putenv('DISPLAY=1');
    $local_machine_helper = $this->localMachineHelper;
    $opened = $local_machine_helper->startBrowser('https://google.com', 'cat');
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
    ];
  }

  /**
   * @dataProvider providerTestExecuteFromCmd()
   *
   * @param $interactive
   * @param $is_tty
   * @param $print_output
   *
   * @throws \Exception
   */
  public function testExecuteFromCmd($interactive, $is_tty, $print_output): void {
    $local_machine_helper = $this->localMachineHelper;
    $local_machine_helper->setIsTty($is_tty);
    $this->input->setInteractive($interactive);
    $process = $local_machine_helper->executeFromCmd('echo "hello world"', NULL, NULL, $print_output);
    $this->assertTrue($process->isSuccessful());
    $buffer = $this->output->fetch();
    if ($print_output === FALSE) {
      $this->assertEmpty($buffer);
    }
    else {
      $this->assertStringContainsString("hello world", $buffer);
    }
  }

  /**
   * @throws \JsonException
   */
  public function testExecuteWithCwd(): void {
    $this->setupFsFixture();
    $local_machine_helper = $this->localMachineHelper;
    $process = $local_machine_helper->execute(['ls', '-lash'], NULL, $this->fixtureDir, FALSE);
    $this->assertTrue($process->isSuccessful());
    $this->assertStringContainsString('xdebug.ini', $process->getOutput());
  }

  public function testCommandExists(): void {
    $local_machine_helper = $this->localMachineHelper;
    $exists = $local_machine_helper->commandExists('cat');
    $this->assertIsBool($exists);
  }

}
