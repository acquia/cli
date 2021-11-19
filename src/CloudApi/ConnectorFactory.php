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
    // If an access token is already defined, use it.
    // Unless a special scope is required.
    //if ($this->config['accessToken'] && !array_key_exists('scope', $this->config)) {
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

    // Otherwise, use a key and secret.
    return new Connector($this->config, $this->baseUri);
  }

  private function createAccessToken() {
    $options = [
      'access_token' => $this->config['accessToken'],
      'expires' => $this->config['accessTokenExpiry'],
    ];

    return new AccessToken($options);
  }

}
