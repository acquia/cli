<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

/**
 * Class CloudServiceTest.
 */
class CloudCredentialsTest extends TestBase {

  /*  public function testLegacyCloudConfig(): void {
  $this->removeMockCloudConfigFile();
  $this->createMockLegacyCloudConfigFile();
  $this->datastoreCloud = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
  $this->cloudCredentials = new CloudCredentials($this->datastoreCloud);
  self::assertEquals($this->key, $this->cloudCredentials->getCloudKey());
  self::assertEquals($this->secret, $this->cloudCredentials->getCloudSecret());
  }*/
}
