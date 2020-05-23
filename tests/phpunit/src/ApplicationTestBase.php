<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\LinkCommand;
use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTestBase extends TestBase {
  protected $app;

  protected function setUp($output = NULL): void {
    parent::setUp($output);
    $this->application->addCommands([new LinkCommand()]);
    $this->app = new ApplicationTester($this->application);
  }

}