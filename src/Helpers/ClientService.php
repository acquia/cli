<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\ConnectorFactory;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\ConnectorInterface;

/**
 * Factory producing Acquia Cloud Api clients.
 *
 * This class is only necessary as a testing shim, so that we can prophesize
 * client queries. Consumers could otherwise just call
 * Client::factory($connector) directly.
 *
 * @package Acquia\Cli\Helpers
 */
class ClientService {

  private $connector;

  public function __construct(ConnectorFactory $connector_factory) {
    $this->setConnector($connector_factory->createConnector());
  }

  public function setConnector(ConnectorInterface $connector): void {
    $this->connector = $connector;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    return Client::factory($this->connector);
  }

}
