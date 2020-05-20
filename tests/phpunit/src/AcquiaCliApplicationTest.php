<?php

namespace Acquia\Cli\Tests;

use Prophecy\Argument;
use Zumba\Amplitude\Amplitude;

class AcquiaCliApplicationTest extends TestBase {

  public function testRun(): void {
    $amplitude = $this->prophet->prophesize(Amplitude::class);
    $this->application->amplitude = $amplitude->reveal();
    $amplitude->queueEvent('Ran command', Argument::type('array'))->shouldBeCalled();
    $exit_code = $this->application->run($this->input, $this->consoleOutput);
    $this->assertEquals(0, $exit_code);
    $this->prophet->checkPredictions();
  }
}