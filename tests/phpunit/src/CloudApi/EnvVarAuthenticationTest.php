<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\Tests\TestBase;

class EnvVarAuthenticationTest extends TestBase {

  protected string $cloudApiBaseUri = 'https://www.acquia.com';

  public function setUp(mixed $output = NULL): void {
    parent::setUp();
    putenv('ACLI_KEY=' . $this->key);
    putenv('ACLI_SECRET=' . $this->secret);
    putenv('ACLI_CLOUD_API_BASE_URI=' . $this->cloudApiBaseUri);
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('ACLI_KEY');
    putenv('ACLI_SECRET');
  }

  public function testKeyAndSecret(): void {
    $this->removeMockCloudConfigFile();
    self::assertEquals($this->key, $this->cloudCredentials->getCloudKey());
    self::assertEquals($this->secret, $this->cloudCredentials->getCloudSecret());
    self::assertEquals($this->cloudApiBaseUri, $this->cloudCredentials->getBaseUri());
  }

}
