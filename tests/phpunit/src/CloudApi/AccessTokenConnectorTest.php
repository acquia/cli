<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Filesystem\Path;

class AccessTokenConnectorTest extends TestBase {

  private static string $accessToken = 'testaccesstoken';

  protected function tearDown(): void {
    parent::tearDown();
    self::unsetAccessTokenEnvVars();
  }

  public static function setAccessTokenEnvVars(mixed $expired = FALSE): void {
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

  public function testAccessToken(): void {
    self::setAccessTokenEnvVars();
    // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
    self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
    $connectorFactory = new ConnectorFactory(
      [
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
        'key' => NULL,
        'secret' => NULL,
      ]);
    $connector = $connectorFactory->createConnector();
    self::assertInstanceOf(AccessTokenConnector::class, $connector);
    self::assertEquals(self::$accessToken, $connector->getAccessToken()->getToken());

    $verb = 'get';
    $path = 'api';

    // Make sure that new access tokens are fetched using the refresh token.
    $mockProvider = $this->prophet->prophesize(GenericProvider::class);
    $mockProvider->getAuthenticatedRequest($verb, ConnectorInterface::BASE_URI . $path, Argument::type(AccessTokenInterface::class))
      ->willReturn($this->prophet->prophesize(RequestInterface::class)->reveal())
      ->shouldBeCalled();
    $connector->setProvider($mockProvider->reveal());
    $connector->createRequest($verb, $path);

    $this->prophet->checkPredictions();
  }

  public function testTokenFile(): void {
    $accessTokenExpiry = time() + 300;
    $directory = [
      'expiry' => (string) $accessTokenExpiry . "\n",
      'token' => self::$accessToken . "\n",
];
    $vfs = vfsStream::setup('root', NULL, $directory);
    $tokenFile = Path::join($vfs->url(), 'token');
    $expiryFile = Path::join($vfs->url(), 'expiry');
    putenv('ACLI_ACCESS_TOKEN_FILE=' . $tokenFile);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiryFile);
    self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
    self::assertEquals($accessTokenExpiry, $this->cloudCredentials->getCloudAccessTokenExpiry());
  }

  public function testMissingTokenFile(): void {
    $accessTokenExpiry = time() + 300;
    $directory = [
      'expiry' => (string) $accessTokenExpiry,
    ];
    $vfs = vfsStream::setup('root', NULL, $directory);
    $tokenFile = Path::join($vfs->url(), 'token');
    $expiryFile = Path::join($vfs->url(), 'expiry');
    putenv('ACLI_ACCESS_TOKEN_FILE=' . $tokenFile);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiryFile);
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Access token file not found at ' . $tokenFile);
    $this->cloudCredentials->getCloudAccessToken();
  }

  public function testMissingExpiryFile(): void {
    $directory = [
      'token' => self::$accessToken,
    ];
    $vfs = vfsStream::setup('root', NULL, $directory);
    $tokenFile = Path::join($vfs->url(), 'token');
    $expiryFile = Path::join($vfs->url(), 'expiry');
    putenv('ACLI_ACCESS_TOKEN_FILE=' . $tokenFile);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE=' . $expiryFile);
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Access token expiry file not found at ' . $expiryFile);
    $this->cloudCredentials->getCloudAccessTokenExpiry();
  }

  /**
   * Validate that if both an access token and API key/secret pair are present,
   * the pair is used.
   */
  public function testConnector(): void {
    self::setAccessTokenEnvVars();
    // Ensure that ACLI_ACCESS_TOKEN was used to populate the refresh token.
    self::assertEquals(self::$accessToken, $this->cloudCredentials->getCloudAccessToken());
    $connectorFactory = new ConnectorFactory(
      [
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
      ]);
    $connector = $connectorFactory->createConnector();
    self::assertInstanceOf(Connector::class, $connector);
  }

  public function testExpiredAccessToken(): void {
    self::setAccessTokenEnvVars(TRUE);
    $connectorFactory = new ConnectorFactory(
      [
        'accessToken' => $this->cloudCredentials->getCloudAccessToken(),
        'accessTokenExpiry' => $this->cloudCredentials->getCloudAccessTokenExpiry(),
        'key' => NULL,
        'secret' => NULL,
      ]);
    $connector = $connectorFactory->createConnector();
    self::assertInstanceOf(Connector::class, $connector);
  }

  public function testConnectorConfig(): void {
    self::setAccessTokenEnvVars();
    $connectorFactory = new ConnectorFactory(
      [
        'accessToken' => NULL,
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
      ]);
    $clientService = new ClientService($connectorFactory, $this->application, $this->cloudCredentials);
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertEquals(['User-Agent' => [0 => 'acli/UNKNOWN']], $options['headers']);

    $this->prophet->checkPredictions();
  }

  public function testIdeHeader(): void {
    self::setAccessTokenEnvVars();
    IdeHelper::setCloudIdeEnvVars();
    $connectorFactory = new ConnectorFactory(
      [
        'accessToken' => NULL,
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
      ]);
    $clientService = new ClientService($connectorFactory, $this->application, $this->cloudCredentials);
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertEquals(['User-Agent' => [0 => 'acli/UNKNOWN'], 'X-Cloud-IDE-UUID' => IdeHelper::$remoteIdeUuid], $options['headers']);

    $this->prophet->checkPredictions();
    IdeHelper::unsetCloudIdeEnvVars();
  }

}
