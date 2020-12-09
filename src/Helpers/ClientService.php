<?php

namespace Acquia\Cli\Helpers;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;

class ClientService {

  private $connector;

  public function __construct(Connector $connector) {
    $this->connector = $connector;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    return Client::factory($this->connector);
  }
}