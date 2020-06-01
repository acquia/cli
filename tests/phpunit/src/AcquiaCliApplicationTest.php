<?php

namespace Acquia\Cli\Tests;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Prophecy\Argument;
use Zumba\Amplitude\Amplitude;

class AcquiaCliApplicationTest extends TestBase {

  public function testAmplitude(): void {
    $this->amplitudeProphecy->queueEvent('Ran command', Argument::type('array'))->shouldBeCalled();
    $this->amplitudeProphecy->init('956516c74386447a3148c2cc36013ac3')->shouldBeCalled();
    $this->amplitudeProphecy->setDeviceId(Argument::type('string'))->shouldBeCalled();
    $this->amplitudeProphecy->setOptOut(TRUE)->shouldBeCalled();
    $this->amplitudeProphecy->logQueuedEvents()->shouldBeCalled();
    // Ensure problems with telemetry reporting are handled silently.
    // This doesn't seem to actually trigger code coverage of the exception catch, why?
    $this->amplitudeProphecy->setUserId()->willThrow(new IdentityProviderException('test', 1, 'test'));
    $exit_code = $this->application->run($this->input, $this->consoleOutput);
    $this->assertEquals(0, $exit_code);
    $this->prophet->checkPredictions();
  }

}