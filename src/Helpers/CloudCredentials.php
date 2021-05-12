<?php

namespace Acquia\Cli\Helpers;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * @package Acquia\Cli\Helpers
 */
class CloudCredentials {

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private $datastoreCloud;

  //public const IDE_REFRESH_TOKEN_FILE_PATH = '/usr/local/share/ide/.refreshtoken';
  public const IDE_REFRESH_TOKEN_FILE_PATH = '/tmp/.refreshtoken';

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
  public function getCloudRefreshToken(): ?string {
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && file_exists(self::IDE_REFRESH_TOKEN_FILE_PATH)) {
      return trim(file_get_contents(self::IDE_REFRESH_TOKEN_FILE_PATH));
    }
    return NULL;
  }

  /**
   * @return string|null
   */
  public function getCloudKey(): ?string {
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
