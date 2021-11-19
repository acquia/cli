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
 * Class AccessTokenConnectorTrait.
 */
trait AccessTokenConnectorTrait {

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

  public static function setAccessTokenEnvVars() {
    $accessTokenExpiry = time() + 300;
    putenv('ACLI_ACCESS_TOKEN=' . self::$accessToken);
    putenv('ACLI_ACCESS_TOKEN_EXPIRY=' . $accessTokenExpiry);
  }

  public static function unsetAccessTokenEnvVars() {
    putenv('ACLI_ACCESS_TOKEN');
    putenv('ACLI_ACCESS_TOKEN_EXPIRY');
  }

}