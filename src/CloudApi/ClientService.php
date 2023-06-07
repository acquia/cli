<?php

declare(strict_types = 1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Application;
use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\ConnectorInterface;

/**
 * Factory producing Acquia Cloud Api clients.
 *
 * This class is only necessary as a testing shim, so that we can prophesize
 * client queries. Consumers could otherwise just call
 * Client::factory($connector) directly.
 */
class ClientService {

  protected ConnectorInterface $connector;
  protected ConnectorFactoryInterface|ConnectorFactory $connectorFactory;
  protected Application $application;
  protected ?bool $machineIsAuthenticated = NULL;

  public function __construct(ConnectorFactoryInterface $connectorFactory, Application $application, protected ApiCredentialsInterface $credentials) {
    $this->connectorFactory = $connectorFactory;
    $this->setConnector($connectorFactory->createConnector());
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
    $userAgent = sprintf("acli/%s", $this->application->getVersion());
    $customHeaders = [
      'User-Agent' => [$userAgent],
    ];
    if ($uuid = getenv("REMOTEIDE_UUID")) {
      $customHeaders['X-Cloud-IDE-UUID'] = $uuid;
    }
    $client->addOption('headers', $customHeaders);
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
