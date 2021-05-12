<?php

namespace Acquia\Cli\CloudApi;

use AcquiaCloudApi\Connector\Connector;

class ConnectorFactory {

  protected $config;
  protected $baseUri;

  /**
   * ConnectorFactory constructor.
   *
   * @param $config
   * @param $base_uri
   */
  public function __construct($config, $base_uri) {
    $this->config = $config;
    $this->baseUri = $base_uri;
  }

  /**
   * @return \Acquia\Cli\CloudApi\RefreshTokenConnector|\AcquiaCloudApi\Connector\Connector
   */
  public function createConnector() {
    if ($this->config['refreshToken']) {
      return new RefreshTokenConnector($this->config, $this->baseUri);
    }

    return new Connector($this->config, $this->baseUri);
  }

}
