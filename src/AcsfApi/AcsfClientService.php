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
   * @return bool
   */
  protected function checkAuthentication(): bool {
    return ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret());
  }

}
