<?php

namespace Acquia\Cli\Tests\Connector;

use Acquia\Cli\Tests\TestBase;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

/**
 * Class CliCloudConnectorTest.
 *
 * @package Acquia\Cli\Tests\Connector
 */
class CliCloudConnectorTest extends TestBase {

  public function testConnector(): void {
    $cloud_client = $this->application->getAcquiaCloudClient();
    try {
      $response = $cloud_client->request('get', 'auth');
    }
    catch (\Exception $e) {
      // This at least tells us we created a success and correctly failed
      // to authenticate.
      $this->assertEquals(get_class($e), IdentityProviderException::class);
    }
  }

}
