<?php

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Tests\TestBase;

/**
 * Class EnvVarAcsfAuthenticationTest.
 */
class EnvVarAcsfAuthenticationTest extends TestBase {

  private string $acsfCurrentFactoryUrl = 'https://www.test.com';

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    putenv('ACSF_USERNAME=' . $this->key);
    putenv('ACSF_PASSWORD=' . $this->secret);
    putenv('ACSF_FACTORY_URI=' . $this->acsfCurrentFactoryUrl);
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('ACSF_USERNAME');
    putenv('ACSF_PASSWORD');
  }

  public function testKeyAndSecret() {
    $this->removeMockCloudConfigFile();
    self::assertEquals($this->key, $this->cloudCredentials->getCloudKey());
    self::assertEquals($this->secret, $this->cloudCredentials->getCloudSecret());
    self::assertEquals($this->acsfCurrentFactoryUrl, $this->cloudCredentials->getBaseUri());
  }

}
