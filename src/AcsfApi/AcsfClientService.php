<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\DataStore\CloudDataStore;

/**
 * AcsfClientService class.
 */
class AcsfClientService extends ClientService {

  /**
   * @param \Acquia\Cli\AcsfApi\AcsfConnectorFactory $connector_factory
   * @param \Acquia\Cli\Application $application
   */
  public function __construct(AcsfConnectorFactory $connector_factory, Application $application) {
    parent::__construct($connector_factory, $application);
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): AcsfClient {
    $client = AcsfClient::factory($this->connector);
    $this->configureClient($client);

    return $client;
  }

  /**
   * @param CloudDataStore $cloud_datastore
   *
   * @return bool
   */
  public function isMachineAuthenticated(CloudDataStore $cloud_datastore): ?bool {
    if ($this->machineIsAuthenticated) {
      return $this->machineIsAuthenticated;
    }

    if (getenv('ACSF_USERNAME') && getenv('ACSF_PASSWORD') ) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $factory = $cloud_datastore->get('acsf_active_factory');
    $keys = $cloud_datastore->get('acsf_factories');
    if ($factory && $keys && array_key_exists($factory, $keys)) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $this->machineIsAuthenticated = FALSE;
    return $this->machineIsAuthenticated;
  }

}
