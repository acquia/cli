<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ClientService;
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

  /**
   * @var string
   */
  private static $accessToken = 'testaccesstoken';

  public function setUp($output = NULL): void {
    parent::setUp();
    self::setAccessTokenEnvVars();
  }

  protected function tearDown(): void {
    parent::tearDown();
    self::unsetAccessTokenEnvVars();
  }

  public static function setAccessTokenEnvVars($expired = FALSE) {
    if ($expired) {
      $accessTokenExpiry = time() - 300;
    }
    else {
      $accessTokenExpiry = time() + 300;
    }
    putenv('ACLI_ACCESS_TOKEN=' . self::$accessToken);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY=' . $accessTokenExpiry);
  }

  public static function unsetAccessTokenEnvVars(): void {
    putenv('ACLI_ACCESS_TOKEN');
    putenv('ACLI_ACCESS_TOKEN_EXPIRY');
  }

  public function testAccessToken(): void {
    // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
    self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
    $connector_factory = new ConnectorFactory(
      [
        'key' => NULL,
        'secret' => NULL,
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

  /**
   * Validate that if both an access token and API key/secret pair are present,
   * the pair is used.
   */
  public function testConnector(): void {
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
    self::assertInstanceOf(Connector::class, $connector);
  }

  public function testExpiredAccessToken(): void {
    self::setAccessTokenEnvVars(TRUE);
    $connector_factory = new ConnectorFactory(
      [
        'key' => NULL,
        'secret' => NULL,
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
      ]);
    $connector = $connector_factory->createConnector();
    self::assertInstanceOf(Connector::class, $connector);
  }

  public function testConnectorConfig(): void {
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => NULL,
      ]);
    $clientService = new ClientService($connector_factory, $this->application, $this->cloudCredentials);
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertArrayHasKey('headers', $options);
    $this->assertArrayHasKey('User-Agent', $options['headers']);

    $this->prophet->checkPredictions();
  }

}
