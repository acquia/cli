<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;

/**
 * AcsfClientService class.
 */
class AcsfClientService extends ClientService {

  public function __construct(AcsfConnectorFactory $connector_factory, Application $application, AcsfCredentials $cloudCredentials) {
    parent::__construct($connector_factory, $application, $cloudCredentials);
  }

  public function getClient(): AcsfClient {
    $client = AcsfClient::factory($this->connector);
    $this->configureClient($client);

    return $client;
  }

  protected function checkAuthentication(): bool {
    return ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret());
  }

}
