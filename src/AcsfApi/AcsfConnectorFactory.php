<?php

declare(strict_types = 1);

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;

class AcsfConnectorFactory implements ConnectorFactoryInterface {

  /**
   * @param array<string> $config
   */
  public function __construct(protected array $config, protected ?string $baseUri = NULL) {
  }

  public function createConnector(): AcsfConnector {
    return new AcsfConnector($this->config, $this->baseUri);
  }

}
