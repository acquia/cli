<?php

namespace Acquia\Cli\AcsfApi;

use Webmozart\KeyValueStore\JsonFileStore;

/**
 * @package Acquia\Cli\Helpers
 */
class AcsfCredentials {

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
   * @return string|null
   */
  public function getCloudKey(): ?string {
    if (getenv('ACSF_KEY')) {
      return getenv('ACSF_KEY');
    }

    if ($this->datastoreCloud->get('acsf_key')) {
      return $this->datastoreCloud->get('acsf_key');
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudSecret(): ?string {
    if (getenv('ACSF_SECRET')) {
      return getenv('ACSF_SECRET');
    }

    $acsf_key = $this->getCloudKey();
    if ($this->datastoreCloud->get('acsf_keys')) {
      $keys = $this->datastoreCloud->get('acsf_keys');
      if (is_array($keys) && array_key_exists($acsf_key, $keys)) {
        return $this->datastoreCloud->get('acsf_keys')[$acsf_key]['secret'];
      }
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getBaseUri(): ?string {
    if (getenv('ACSF_API_BASE_URI')) {
      return getenv('ACSF_API_BASE_URI');
    }
    return NULL;
  }

}
