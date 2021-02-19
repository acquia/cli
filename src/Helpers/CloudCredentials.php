<?php

namespace Acquia\Cli\Helpers;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * @package Acquia\Cli\Helpers
 */
class CloudCredentials {

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private $datastoreCloud;

  /**
   * CloudCredentials constructor.
   *
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastoreCloud
   */
  public function __construct(JsonFileStore $datastoreCloud) {
    $this->datastoreCloud = $datastoreCloud;
  }

  /**
   * @return array|mixed|string|null
   */
  public function getCloudKey() {
    return $this->datastoreCloud->get('acli_key');
  }

  /**
   * @return mixed
   */
  public function getCloudSecret() {
    $acli_key = $this->getCloudKey();
    return $this->datastoreCloud->get('keys')[$acli_key]['secret'];
  }

}
