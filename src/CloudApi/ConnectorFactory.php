<?php

namespace Acquia\Cli\CloudApi;

use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Token\AccessToken;

class ConnectorFactory {

  protected $config;
  protected $baseUri;

  /**
   * ConnectorFactory constructor.
   *
   * @param $config
   * @param $base_uri
   */
  public function __construct($config, $base_uri = NULL) {
    $this->setConfig($config);
    $this->baseUri = $base_uri;
  }

  /**
   * @param mixed $config
   */
  public function setConfig($config): void {
    $this->config = $config;
  }

  /**
   * @return mixed
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * @return \Acquia\Cli\CloudApi\AccessTokenConnector|\AcquiaCloudApi\Connector\Connector
   */
  public function createConnector() {
    // A defined key & secret takes priority.
    if ($this->config['key'] && $this->config['secret']) {
      return new Connector($this->config, $this->baseUri);
    }

    // Fall back to a valid access token.
    if ($this->config['accessToken']) {
      $access_token = $this->createAccessToken();
      if (!$access_token->hasExpired()) {
        return new AccessTokenConnector([
          'access_token' => $access_token,
          'key' => NULL,
          'secret' => NULL,
        ], $this->baseUri);
      }
    }

    // Fall back to an unauthenticated request.
    return new Connector($this->config, $this->baseUri);
  }

  private function createAccessToken() {
    return new AccessToken([
      'access_token' => $this->config['accessToken'],
      'expires' => $this->config['accessTokenExpiry'],
    ]);
  }

}
