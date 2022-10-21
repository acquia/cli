<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Class AccessTokenConnectorTest.
 */
class AccessTokenConnectorTest extends TestBase {

  private static string $accessToken = 'testaccesstoken';

  protected function tearDown(): void {
    parent::tearDown();
    self::unsetAccessTokenEnvVars();
  }

  public static function setAccessTokenEnvVars($expired = FALSE): void {
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
    putenv('ACLI_ACCESS_TOKEN_FILE');
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE');
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function testAccessToken(): void {
    self::setAccessTokenEnvVars();
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function testTokenFile(): void {
    $accessTokenExpiry = time() + 300;
    $directory = [
      'token' => self::$accessToken . "\n",
      'expiry' => (string) $accessTokenExpiry . "\n"
    ];
    $vfs = vfsStream::setup('root', NULL, $directory);
    $token_file = Path::join($vfs->url(), 'token');
    $expiry_file = Path::join($vfs->url(), 'expiry');
    putenv('ACLI_ACCESS_TOKEN_FILE=' . $token_file);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiry_file);
    self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
    self::assertEquals($accessTokenExpiry, $this->cloudCredentials->getCloudAccessTokenExpiry());
  }

  public function testMissingTokenFile(): void {
    $accessTokenExpiry = time() + 300;
    $directory = [
      'expiry' => (string) $accessTokenExpiry
    ];
    $vfs = vfsStream::setup('root', NULL, $directory);
    $token_file = Path::join($vfs->url(), 'token');
    $expiry_file = Path::join($vfs->url(), 'expiry');
    putenv('ACLI_ACCESS_TOKEN_FILE=' . $token_file);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiry_file);
    try {
      $this->cloudCredentials->getCloudAccessToken();
    }
    catch (AcquiaCliException $exception) {
      self::assertEquals('Access token file not found at ' . $token_file, $exception->getMessage());
    }
  }

  public function testMissingExpiryFile(): void {
    $directory = [
      'token' => self::$accessToken,
    ];
    $vfs = vfsStream::setup('root', NULL, $directory);
    $token_file = Path::join($vfs->url(), 'token');
    $expiry_file = Path::join($vfs->url(), 'expiry');
    putenv('ACLI_ACCESS_TOKEN_FILE=' . $token_file);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiry_file);
    try {
      $this->cloudCredentials->getCloudAccessTokenExpiry();
    }
    catch (AcquiaCliException $exception) {
      self::assertEquals('Access token expiry file not found at ' . $expiry_file, $exception->getMessage());
    }
  }

  /**
   * Validate that if both an access token and API key/secret pair are present,
   * the pair is used.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function testConnector(): void {
    self::setAccessTokenEnvVars();
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

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
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
    self::setAccessTokenEnvVars();
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => NULL,
      ]);
    $clientService = new ClientService($connector_factory, $this->application, $this->cloudCredentials);
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertEquals(['User-Agent' => [0 => 'acli/UNKNOWN']], $options['headers']);

    $this->prophet->checkPredictions();
  }

}
