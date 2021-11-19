<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Application;
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
  private $application;

  public function __construct(ConnectorFactory $connector_factory, Application $application) {
    $this->setConnector($connector_factory->createConnector());
    $this->setApplication($application);
  }

  public function setConnector(ConnectorInterface $connector): void {
    $this->connector = $connector;
  }

  public function setApplication(Application $application): void {
    $this->application = $application;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    $client = Client::factory($this->connector);
    $user_agent = sprintf("acli/%s", $this->application->getVersion());
    $client->addOption('headers', [
      'User-Agent' => [$user_agent],
    ]);

    return $client;
  }

}
