<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeRequiredTestBase.
 */
trait IdeRequiredTestTrait {

  /**
   * This method is called before each test.
   */
  public function setUp(OutputInterface $output = NULL): void {
    parent::setUp();
    IdeHelper::setCloudIdeEnvVars();
  }

  public function tearDown(): void {
    parent::tearDown();
    IdeHelper::unsetCloudIdeEnvVars();
  }

}
