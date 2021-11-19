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
 * Class AccessTokenConnectorTest.
 */
class AccessTokenConnectorTest extends TestBase {

  use AccessTokenConnectorTrait;

  public function testAccessToken() {
    // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
    self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
      ]);
    $connector = $connector_factory->createConnector();
    self::assertInstanceOf(AccessTokenConnector::class, $connector);
    self::assertEquals(self::$accessToken, $connector->getAccessToken()->getToken());

    $verb = 'get';
    $path = 'api';

    // Make sure that new access tokens are fetched using the refresh token.
    $mock_provider = $this->prophet->prophesize(GenericProvider::class);
    $mock_provider->getAuthenticatedRequest($verb, ConnectorInterface::BASE_URI . $path, Argument::type(AccessTokenInterface::class))
      ->willReturn($this->prophet->prophesize(RequestInterface::class)->reveal())
      ->shouldBeCalled();
    $connector->setProvider($mock_provider->reveal());
    $connector->createRequest($verb, $path);

    $this->prophet->checkPredictions();
  }

  public function testExpiredAccessToken() {
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
      ]);
    $connector = $connector_factory->createConnector();
    self::assertInstanceOf(Connector::class, $connector);
  }

}
