<?php

declare(strict_types = 1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Token\AccessToken;

class ConnectorFactory implements ConnectorFactoryInterface {

  /**
   * @param array<string> $config
   */
  public function __construct(protected array $config, protected ?string $baseUri = NULL, protected ?string $accountsUri = NULL) {
  }

  /**
   * @return \Acquia\Cli\CloudApi\AccessTokenConnector|\AcquiaCloudApi\Connector\Connector
   */
  public function createConnector(): Connector|AccessTokenConnector {
    // A defined key & secret takes priority.
    if ($this->config['key'] && $this->config['secret']) {
      return new Connector($this->config, $this->baseUri, $this->accountsUri);
    }

    // Fall back to a valid access token.
    if ($this->config['accessToken']) {
      $accessToken = $this->createAccessToken();
      if (!$accessToken->hasExpired()) {
        // @todo Add debug log entry indicating that access token is being used.
        return new AccessTokenConnector([
          'access_token' => $accessToken,
          'key' => NULL,
          'secret' => NULL,
        ], $this->baseUri, $this->accountsUri, 'organization:' . getenv('AH_ORGANIZATION_UUID'));
      }
    }

    // Fall back to an unauthenticated request.
    return new Connector($this->config, $this->baseUri, $this->accountsUri);
  }

  private function createAccessToken(): AccessToken {
    return new AccessToken([
      'access_token' => $this->config['accessToken'],
      'expires' => $this->config['accessTokenExpiry'],
    ]);
  }

}
