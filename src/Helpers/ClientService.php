<?php

namespace Acquia\Cli\Helpers;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use Webmozart\KeyValueStore\JsonFileStore;

class ClientService {

  private $acquiaCloudClient;

  private $cloud_api_conf;

  public function __construct(JsonFileStore $datastoreCloud) {
    $this->cloud_api_conf = $datastoreCloud;
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