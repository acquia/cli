<?php

namespace Acquia\Cli\CloudApi;

use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Class AccessTokenConnector
 */
class AccessTokenConnector extends Connector {

  /**
   * @var \League\OAuth2\Client\Provider\GenericProvider
   */
  protected $provider;

  /**
   * @inheritdoc
   */
  public function __construct(array $config, string $base_uri = NULL) {
    $this->accessToken = new AccessToken([
      'access_token' => $config['accessToken'],
      'expires' => $config['accessTokenExpiry'],
    ]);

    parent::__construct($config, $base_uri);
  }

  /**
   * @inheritdoc
   */
  public function createRequest($verb, $path) {
    return $this->provider->getAuthenticatedRequest(
      $verb,
      $this->baseUri . $path,
      $this->accessToken
    );
  }

  /**
   * @param \League\OAuth2\Client\Provider\GenericProvider $provider
   */
  public function setProvider(
    GenericProvider $provider
  ): void {
    $this->provider = $provider;
  }

  /**
   * @return \League\OAuth2\Client\Token\AccessToken
   */
  public function getAccessToken(): AccessToken {
    return $this->accessToken;
  }

}
