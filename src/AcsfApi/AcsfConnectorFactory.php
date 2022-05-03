<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;

/**
 * AcsfConnectorFactory class.
 */
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

  /**
   * @return \Acquia\Cli\AcsfApi\AcsfConnector
   */
  public function createConnector(): AcsfConnector {
    return new AcsfConnector($this->config, $this->baseUri);
  }

}
