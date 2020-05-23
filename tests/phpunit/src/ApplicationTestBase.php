<?php

namespace Acquia\Cli\Tests;

use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTestBase extends TestBase {
  protected $app;

  protected function setUp($output = NULL): void {
    parent::setUp($output);
    $this->app = new ApplicationTester($this->application);
  }

}