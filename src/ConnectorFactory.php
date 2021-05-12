<?php

namespace Acquia\Cli;

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
   * @return \Acquia\Cli\RefreshConnector|\AcquiaCloudApi\Connector\Connector
   */
  public function createConnector() {
    if ($this->config['refreshToken']) {
      return new RefreshConnector($this->config, $this->baseUri);
    }

    return new Connector($this->config, $this->baseUri);
  }

}
