<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
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
class AccessTokenConnectorTest extends TestBase {

  /**
   * @var string
   */
  private $accessToken;

  /**
   * @var int
   */
  private $accessTokenExpiry;

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->accessToken = 'testaccesstoken';
    $this->accessTokenExpiry = time() + 300;
    putenv('ACLI_ACCESS_TOKEN=' . $this->accessToken);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY=' . $this->accessTokenExpiry);
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('ACLI_ACCESS_TOKEN');
    putenv('ACLI_ACCESS_TOKEN_EXPIRY');
  }

  public function testAccessToken() {
    // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
    self::assertEquals($this->accessToken, $this->cloudCredentials->getCloudAccessToken());
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
      ]);
    $connector = $connector_factory->createConnector();
    self::assertInstanceOf(AccessTokenConnector::class, $connector);
    self::assertEquals($this->accessToken, $connector->getAccessToken()->getToken());

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
    $this->accessTokenExpiry = time() - 300;
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
