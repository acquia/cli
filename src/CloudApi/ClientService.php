<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Application;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use Webmozart\KeyValueStore\JsonFileStore;

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

  /** @var ConnectorInterface */
  private $connector;
  /** @var \Acquia\Cli\CloudApi\ConnectorFactory */
  private $connectorFactory;
  /** @var Application */
  private $application;
  /** @var bool */
  private $machineIsAuthenticated = NULL;

  /**
   * @param \Acquia\Cli\CloudApi\ConnectorFactory $connector_factory
   * @param \Acquia\Cli\Application $application
   */
  public function __construct(ConnectorFactory $connector_factory, Application $application) {
    $this->connectorFactory = $connector_factory;
    $this->setConnector($connector_factory->createConnector());
    $this->setApplication($application);
  }

  /**
   * @param \AcquiaCloudApi\Connector\ConnectorInterface $connector
   */
  public function setConnector(ConnectorInterface $connector): void {
    $this->connector = $connector;
  }

  /**
   * @param \Acquia\Cli\Application $application
   */
  public function setApplication(Application $application): void {
    $this->application = $application;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    $client = Client::factory($this->connector);
    $this->configureClient($client);

    return $client;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $client
   */
  protected function configureClient(Client $client): void {
    $user_agent = sprintf("acli/%s", $this->application->getVersion());
    $client->addOption('headers', [
      'User-Agent' => [$user_agent],
    ]);
  }

  /**
   * @param JsonFileStore $cloud_datastore
   *
   * @return bool
   */
  public function isMachineAuthenticated(JsonFileStore $cloud_datastore): ?bool {
    if ($this->machineIsAuthenticated) {
      return $this->machineIsAuthenticated;
    }

    if (getenv('ACLI_ACCESS_TOKEN')) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    if (getenv('ACLI_KEY') && getenv('ACLI_SECRET') ) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $acli_key = $cloud_datastore->get('acli_key');
    $keys = $cloud_datastore->get('keys');
    if ($acli_key && $keys && array_key_exists($acli_key, $keys)) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $this->machineIsAuthenticated = FALSE;
    return $this->machineIsAuthenticated;
  }

}
