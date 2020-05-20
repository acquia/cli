<?php

namespace Acquia\Cli\Tests;

use Zumba\Amplitude\Amplitude;

class AcquiaCliApplicationTest extends TestBase {

  public function testRun(): void {
    $this->application->amplitude = $this->getMockBuilder(Amplitude::class)
      ->onlyMethods(['queueEvent'])
      ->getMock();
    $this->application->amplitude->expects($this->exactly(1))->method('queueEvent');
    $exit_code = $this->application->run($this->input, $this->consoleOutput);
    $this->assertEquals(0, $exit_code);
  }
}