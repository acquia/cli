<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;

/**
 * AcsfConnectorFactory class.
 */
class AcsfConnectorFactory implements ConnectorFactoryInterface {

  protected ?string $baseUri;

  /**
   * ConnectorFactory constructor.
   *
   * @param array $config
   * @param string|null $base_uri
   */
  public function __construct(protected array $config, string $base_uri = NULL) {
    $this->baseUri = $base_uri;
  }

  public function createConnector(): AcsfConnector {
    return new AcsfConnector($this->config, $this->baseUri);
  }

}
