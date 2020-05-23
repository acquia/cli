<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;

class ExceptionApplicationTest extends ApplicationTestBase {

  public function testInvalidApiCreds(): void {
    $cloud_client = $this->getMockClient();
    $cloud_client->request('get', '/applications')
      ->willReturn('invalid_client')
      ->shouldBeCalled();
    $this->app->run(['link'], ['interactive' => FALSE]);
    $output = $this->app->getDisplay();
    $this->assertStringContainsString("Your Cloud API credentials are invalid. Run acli auth:login to reset them.", $output);
  }
}