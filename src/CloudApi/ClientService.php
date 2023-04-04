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

  /**
   * @param \Acquia\Cli\CloudApi\ConnectorFactory $connector_factory
   */
  public function __construct(ConnectorFactoryInterface $connector_factory, Application $application, protected ApiCredentialsInterface $credentials) {
    $this->connectorFactory = $connector_factory;
    $this->setConnector($connector_factory->createConnector());
    $this->setApplication($application);
  }

  public function setConnector(ConnectorInterface $connector): void {
    $this->connector = $connector;
  }

  private function setApplication(Application $application): void {
    $this->application = $application;
  }

  public function getClient(): Client {
    $client = Client::factory($this->connector);
    $this->configureClient($client);

    return $client;
  }

  protected function configureClient(Client $client): void {
    $user_agent = sprintf("acli/%s", $this->application->getVersion());
    $custom_headers = [
      'User-Agent' => [$user_agent],
    ];
    if ($uuid = getenv("REMOTEIDE_UUID")) {
      $custom_headers['X-Cloud-IDE-UUID'] = $uuid;
    }
    $client->addOption('headers', $custom_headers);
  }

  public function isMachineAuthenticated(): bool {
    if ($this->machineIsAuthenticated !== NULL) {
      return $this->machineIsAuthenticated;
    }
    $this->machineIsAuthenticated = $this->checkAuthentication();
    return $this->machineIsAuthenticated;
  }

  protected function checkAuthentication(): bool {
    return (
      $this->credentials->getCloudAccessToken() ||
      ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret())
    );
  }

}
