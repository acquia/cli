<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
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
class AcsfClientService extends ClientService {

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    $client = Client::factory($this->connector);
    $this->configureClient($client);

    return $client;
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

    if (getenv('ACSF_KEY') && getenv('ACSF_SECRET') ) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $acsf_key = $cloud_datastore->get('acsf_key');
    $keys = $cloud_datastore->get('acsf_keys');
    if ($acsf_key && $keys && array_key_exists($acsf_key, $keys)) {
      $this->machineIsAuthenticated = TRUE;
      return $this->machineIsAuthenticated;
    }

    $this->machineIsAuthenticated = FALSE;
    return $this->machineIsAuthenticated;
  }
}