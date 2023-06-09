<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

class LocalMachineHelperTest extends TestBase {

  public function testStartBrowser(): void {
    putenv('DISPLAY=1');
    $localMachineHelper = $this->localMachineHelper;
    $opened = $localMachineHelper->startBrowser('https://google.com', 'cat');
    $this->assertTrue($opened, 'Failed to open browser');
    putenv('DISPLAY');
  }

  /**
   * @return bool[][]
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
   */
  public function testExecuteFromCmd(bool $interactive, bool|NULL $isTty, bool|NULL $printOutput): void {
    $localMachineHelper = $this->localMachineHelper;
    $localMachineHelper->setIsTty($isTty);
    $this->input->setInteractive($interactive);
    $process = $localMachineHelper->executeFromCmd('echo "hello world"', NULL, NULL, $printOutput);
    $this->assertTrue($process->isSuccessful());
    assert(is_a($this->output, BufferedOutput::class));
    $buffer = $this->output->fetch();
    if ($printOutput === FALSE) {
      $this->assertEmpty($buffer);
    }
    else {
      $this->assertStringContainsString("hello world", $buffer);
    }
  }

  public function testExecuteWithCwd(): void {
    $this->setupFsFixture();
    $localMachineHelper = $this->localMachineHelper;
    $process = $localMachineHelper->execute(['ls', '-lash'], NULL, $this->fixtureDir, FALSE);
    $this->assertTrue($process->isSuccessful());
    $this->assertStringContainsString('xdebug.ini', $process->getOutput());
  }

  public function testCommandExists(): void {
    $localMachineHelper = $this->localMachineHelper;
    $exists = $localMachineHelper->commandExists('cat');
    $this->assertIsBool($exists);
  }

}
