<?php

namespace Acquia\Cli\AcsfApi;

use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Token\AccessToken;

class AcsfConnectorFactory {

  protected $config;
  protected $baseUri;

  /**
   * ConnectorFactory constructor.
   *
   * @param array $config
   * @param string $base_uri
   */
  public function __construct($config, $base_uri = NULL) {
    $this->config = $config;
    $this->baseUri = $base_uri;
  }

  /**
   * @return \Acquia\Cli\CloudApi\AccessTokenConnector|\AcquiaCloudApi\Connector\Connector
   */
  public function createConnector() {
    // A defined key & secret takes priority.
    if ($this->config['key'] && $this->config['secret']) {
      return new Connector($this->config, $this->baseUri);
    }

    // Fall back to an unauthenticated request.
    return new Connector($this->config, $this->baseUri);
  }

}
