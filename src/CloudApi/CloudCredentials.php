<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;

/**
 * @package Acquia\Cli\Helpers
 */
class CloudCredentials implements ApiCredentialsInterface {

  private CloudDataStore $datastoreCloud;

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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function getCloudAccessToken(): ?string {
    if ($token = getenv('ACLI_ACCESS_TOKEN')) {
      return $token;
    }

    if ($file = getenv('ACLI_ACCESS_TOKEN_FILE')) {
      if (!file_exists($file)) {
        throw new AcquiaCliException('Access token file not found at {file}', ['file' => $file]);
      }
      return trim(file_get_contents($file), "\"\n");
    }

    return NULL;
  }

  /**
   * @return string|null
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function getCloudAccessTokenExpiry(): ?string {
    if ($token = getenv('ACLI_ACCESS_TOKEN_EXPIRY')) {
      return $token;
    }

    if ($file = getenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE')) {
      if (!file_exists($file)) {
        throw new AcquiaCliException('Access token expiry file not found at {file}', ['file' => $file]);
      }
      return trim(file_get_contents($file), "\"\n");
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudKey(): ?string {
    if ($key = getenv('ACLI_KEY')) {
      return $key;
    }

    if ($this->datastoreCloud->get('acli_key')) {
      return $this->datastoreCloud->get('acli_key');
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudSecret(): ?string {
    if ($secret = getenv('ACLI_SECRET')) {
      return $secret;
    }

    $acli_key = $this->getCloudKey();
    if ($this->datastoreCloud->get('keys')) {
      $keys = $this->datastoreCloud->get('keys');
      if (is_array($keys) && array_key_exists($acli_key, $keys)) {
        return $this->datastoreCloud->get('keys')[$acli_key]['secret'];
      }
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  public function getBaseUri(): ?string {
    if ($uri = getenv('ACLI_CLOUD_API_BASE_URI')) {
      return $uri;
    }
    return NULL;
  }

}
