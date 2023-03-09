<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;

/**
 * AcsfConnectorFactory class.
 */
class AcsfConnectorFactory implements ConnectorFactoryInterface {

  protected array $config;
  protected ?string $baseUri;

  /**
   * ConnectorFactory constructor.
   *
   * @param array $config
   * @param string|null $base_uri
   */
  public function __construct(array $config, string $base_uri = NULL) {
    $this->config = $config;
    $this->baseUri = $base_uri;
  }

  public function createConnector(): AcsfConnector {
    return new AcsfConnector($this->config, $this->baseUri);
  }

}
