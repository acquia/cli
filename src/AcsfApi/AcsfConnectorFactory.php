<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Token\AccessToken;

class AcsfConnectorFactory implements ConnectorFactoryInterface {

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

  public function createConnector() {
    return new AcsfConnector($this->config, $this->baseUri);
  }

}
