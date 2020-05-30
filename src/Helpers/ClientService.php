<?php

namespace Acquia\Cli\Helpers;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;

class ClientService {

  private $acquiaCloudClient;

  private $cloud_api_conf;

  public function __construct($cloud_api_conf) {
    $this->cloud_api_conf = $cloud_api_conf;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    if (isset($this->acquiaCloudClient)) {
      return $this->acquiaCloudClient;
    }

    $config = [
      'key' => $this->cloud_api_conf->get('key'),
      'secret' => $this->cloud_api_conf->get('secret'),
    ];
    $this->acquiaCloudClient = Client::factory(new Connector($config));

    return $this->acquiaCloudClient;
  }
}