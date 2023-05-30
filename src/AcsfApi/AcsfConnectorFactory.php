<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;

class AcsfConnectorFactory implements ConnectorFactoryInterface {

  public function __construct(protected array $config, protected ?string $baseUri = NULL) {
  }

  public function createConnector(): AcsfConnector {
    return new AcsfConnector($this->config, $this->baseUri);
  }

}
