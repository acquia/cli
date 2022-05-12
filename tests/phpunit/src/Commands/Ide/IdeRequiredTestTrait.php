<?php

namespace Acquia\Cli\Tests\Commands\Ide;

/**
 * Class IdeRequiredTestBase.
 */
trait IdeRequiredTestTrait {

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   */
  public function setUp($output = NULL): void {
    parent::setUp();
    IdeHelper::setCloudIdeEnvVars();
  }

  public function tearDown(): void {
    parent::tearDown();
    IdeHelper::unsetCloudIdeEnvVars();
  }

}
