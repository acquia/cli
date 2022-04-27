<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\DataStore\CloudDataStore;

/**
 * @package Acquia\Cli\Helpers
 */
class CloudCredentials implements ApiCredentialsInterface {

  /**
   * @var \Acquia\Cli\DataStore\CloudDataStore
   */
  private $datastoreCloud;

  /**
   * CloudCredentials constructor.
   *
   * @param \Acquia\Cli\DataStore\CloudDataStore $datastoreCloud
   */
  public function __construct(CloudDataStore $datastoreCloud) {
    $this->datastoreCloud = $datastoreCloud;
  }

  /**
   * @return string|null
   */
  public function getCloudAccessToken(): ?string {
    if (getenv('ACLI_ACCESS_TOKEN')) {
      return getenv('ACLI_ACCESS_TOKEN');
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudAccessTokenExpiry(): ?string {
    if (getenv('ACLI_ACCESS_TOKEN_EXPIRY')) {
      return getenv('ACLI_ACCESS_TOKEN_EXPIRY');
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudKey(): ?string {
    if (getenv('ACLI_KEY')) {
      return getenv('ACLI_KEY');
    }

    if ($this->datastoreCloud->get('acli_key')) {
      return $this->datastoreCloud->get('acli_key');
    }

    // Legacy format.
    if ($this->datastoreCloud->get('key') &&
      $this->datastoreCloud->get('secret')) {
      return $this->datastoreCloud->get('key');
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudSecret(): ?string {
    if (getenv('ACLI_SECRET')) {
      return getenv('ACLI_SECRET');
    }

    $acli_key = $this->getCloudKey();
    if ($this->datastoreCloud->get('keys')) {
      $keys = $this->datastoreCloud->get('keys');
      if (is_array($keys) && array_key_exists($acli_key, $keys)) {
        return $this->datastoreCloud->get('keys')[$acli_key]['secret'];
      }
    }

    // Legacy format.
    if ($this->datastoreCloud->get('key') &&
      $this->datastoreCloud->get('secret')) {
      return $this->datastoreCloud->get('secret');
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getBaseUri(): ?string {
    if (getenv('ACLI_CLOUD_API_BASE_URI')) {
      return getenv('ACLI_CLOUD_API_BASE_URI');
    }
    return NULL;
  }

}
