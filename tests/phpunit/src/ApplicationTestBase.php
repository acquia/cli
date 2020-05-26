<?php

namespace Acquia\Cli\Tests;

use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTestBase extends TestBase {
  protected $applicationTester;

  protected function setUp($output = NULL): void {
    parent::setUp($output);
    $this->applicationTester = new ApplicationTester($this->application);
  }

}