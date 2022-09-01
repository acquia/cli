<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;

/**
 * AcsfClientService class.
 */
class AcsfClientService extends ClientService {

  /**
   * @param \Acquia\Cli\AcsfApi\AcsfConnectorFactory $connector_factory
   * @param \Acquia\Cli\Application $application
   * @param \Acquia\Cli\AcsfApi\AcsfCredentials $cloudCredentials
   */
  public function __construct(AcsfConnectorFactory $connector_factory, Application $application, AcsfCredentials $cloudCredentials) {
    parent::__construct($connector_factory, $application, $cloudCredentials);
  }

  /**
   * @return \Acquia\Cli\AcsfApi\AcsfClient
   */
  public function getClient(): AcsfClient {
    $client = AcsfClient::factory($this->connector);
    $this->configureClient($client);

    return $client;
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

    if ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret()) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $this->machineIsAuthenticated = FALSE;
    return $this->machineIsAuthenticated;
  }

}
