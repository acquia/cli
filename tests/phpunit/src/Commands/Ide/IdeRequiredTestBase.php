<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Tests\CommandTestBase;

/**
 * Class IdeTestBase.
 */
abstract class IdeRequiredTestBase extends CommandTestBase {

  /**
   * @var string
   */
  protected $remote_ide_uuid;

  /**
   * @var string
   */
  protected $application_uuid;

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    parent::setUp();
    $this->remote_ide_uuid = '4ba6c569-5084-4b6d-9467-019ccb5dc847';
    $this->application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $this->setCloudIdeEnvVars();
  }

  protected function tearDown(): void {
    parent::tearDown();
    $this->unsetCloudIdeEnvVars();
  }

  protected function getCloudIdeEnvVars(): array {
    return [
      'REMOTEIDE_UUID' => $this->remote_ide_uuid,
      'ACQUIA_APPLICATION_UUID' => $this->application_uuid,
      'ACQUIA_USER_UUID' => '4acf8956-45df-3cf4-5106-065b62cf1ac8',
      'AH_SITE_ENVIRONMENT' => 'IDE',
    ];
  }

  protected function setCloudIdeEnvVars(): void {
    foreach ($this->getCloudIdeEnvVars() as $key => $value) {
      putenv($key . '=' . $value);
    }
  }

  protected function unsetCloudIdeEnvVars(): void {
    foreach ($this->getCloudIdeEnvVars() as $key => $value) {
      putenv($key);
    }
  }

}
