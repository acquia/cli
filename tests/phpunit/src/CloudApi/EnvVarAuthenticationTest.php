<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;

/**
 * Class EnvVarAuthenticationTest.
 */
class EnvVarAuthenticationTest extends TestBase {

  protected string $cloudApiBaseUri = 'https://www.acquia.com';

  public function setUp($output = NULL): void {
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

  public function testKeyAndSecret() {
    $this->removeMockCloudConfigFile();
    self::assertEquals($this->key, $this->cloudCredentials->getCloudKey());
    self::assertEquals($this->secret, $this->cloudCredentials->getCloudSecret());
    self::assertEquals($this->cloudApiBaseUri, $this->cloudCredentials->getBaseUri());
  }

}
