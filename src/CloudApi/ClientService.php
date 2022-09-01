<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Application;
use Acquia\Cli\ClientServiceInterface;
use Acquia\Cli\ConnectorFactoryInterface;
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
class ClientService implements ClientServiceInterface {

  protected ConnectorInterface $connector;
  protected ConnectorFactoryInterface|ConnectorFactory $connectorFactory;
  protected Application $application;
  protected ?bool $machineIsAuthenticated = NULL;
  protected ApiCredentialsInterface $credentials;

  /**
   * @param \Acquia\Cli\CloudApi\ConnectorFactory $connector_factory
   * @param \Acquia\Cli\Application $application
   * @param \Acquia\Cli\ApiCredentialsInterface $credentials
   */
  public function __construct(ConnectorFactoryInterface $connector_factory, Application $application, ApiCredentialsInterface $credentials) {
    $this->connectorFactory = $connector_factory;
    $this->setConnector($connector_factory->createConnector());
    $this->setApplication($application);
    $this->credentials = $credentials;
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
  private function setApplication(Application $application): void {
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
   * @param CloudDataStore $cloud_datastore
   *
   * @return bool|null
   */
  public function isMachineAuthenticated(): ?bool {
    if ($this->machineIsAuthenticated) {
      return $this->machineIsAuthenticated;
    }

    if ($this->credentials->getCloudAccessToken()) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    if ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret()) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $this->machineIsAuthenticated = FALSE;
    return $this->machineIsAuthenticated;
  }

}
