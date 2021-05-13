<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\CloudApi\RefreshTokenConnector;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\ConnectorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * Class RefreshTokenTest.
 */
class RefreshTokenConnectorTest extends TestBase {

  /**
   * @var string
   */
  private $refreshToken;

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->refreshToken = 'testrefeshtoken';
    putenv('ACLI_REFRESH_TOKEN=' . $this->refreshToken);
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('ACLI_REFRESH_TOKEN');
  }

  public function testRefreshToken() {
    // Ensure that ACLI_REFRESH_TOKEN was used to populate the refresh token.
    self::assertEquals($this->refreshToken, $this->cloudCredentials->getCloudRefreshToken());
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'refreshToken' => $this->cloudCredentials->getCloudRefreshToken(),
      ],
      $this->cloudCredentials->getBaseUri());
    $connector = $connector_factory->createConnector();
    self::assertInstanceOf(RefreshTokenConnector::class, $connector);

    $verb = 'get';
    $path = 'api';
    $options = [];

    $mock_client = $this->prophet->prophesize(Client::class);
    $mock_client->send(Argument::type(RequestInterface::class), Argument::type('array'))->shouldBeCalled();
    $connector->setClient($mock_client->reveal());

    // Make sure that new access tokens are fetched using the refresh token.
    $mock_provider = $this->prophet->prophesize(GenericProvider::class);
    $mock_provider->getAccessToken('refresh_token', ['refresh_token' => $this->refreshToken])
      ->willReturn($this->prophet->prophesize(AccessTokenInterface::class)->reveal())
      ->shouldBeCalled();
    $mock_provider->getAuthenticatedRequest($verb, Argument::type('string'), Argument::type(AccessTokenInterface::class))
      ->willReturn($this->prophet->prophesize(RequestInterface::class)->reveal())
      ->shouldBeCalled();
    $connector->setProvider($mock_provider->reveal());

    // Set cache to Null.
    $connector->setCache(new NullAdapter());
    $connector->sendRequest($verb, $path, $options);

    $this->prophet->checkPredictions();
  }

}
