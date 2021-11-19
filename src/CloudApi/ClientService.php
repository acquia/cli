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

  /** @var ConnectorInterface */
  private $connector;
  /** @var \Acquia\Cli\CloudApi\ConnectorFactory */
  private $connectorFactory;
  /** @var Application */
  private $application;
  /** @var string */
  private $organizationUuid;

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
   * @param $organization_uuid
   */
  public function recreateConnectorWithOrganizationScope($organization_uuid) {
    $this->organizationUuid = $organization_uuid;
    $connector_config = $this->connectorFactory->getConfig();
    $connector_config['organization'] = $organization_uuid;
    $this->connectorFactory->setConfig($connector_config);
    $this->setConnector($this->connectorFactory->createConnector());
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

    if (isset($this->organizationUuid)) {
      $client->addQuery('scope', 'organization:' . $this->organizationUuid);
    }
  }

}
