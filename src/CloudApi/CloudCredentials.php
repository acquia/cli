<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;

class CloudCredentials implements ApiCredentialsInterface {

  /**
   * CloudCredentials constructor.
   */
  public function __construct(private CloudDataStore $datastoreCloud) {
  }

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

  public function getCloudKey(): ?string {
    if ($key = getenv('ACLI_KEY')) {
      return $key;
    }

    if ($this->datastoreCloud->get('acli_key')) {
      return $this->datastoreCloud->get('acli_key');
    }

    return NULL;
  }

  public function getCloudSecret(): ?string {
    if ($secret = getenv('ACLI_SECRET')) {
      return $secret;
    }

    $acliKey = $this->getCloudKey();
    if ($this->datastoreCloud->get('keys')) {
      $keys = $this->datastoreCloud->get('keys');
      if (is_array($keys) && array_key_exists($acliKey, $keys)) {
        return $this->datastoreCloud->get('keys')[$acliKey]['secret'];
      }
    }

    return NULL;
  }

  public function getBaseUri(): ?string {
    if ($uri = getenv('ACLI_CLOUD_API_BASE_URI')) {
      return $uri;
    }
    return NULL;
  }

  public function getAccountsUri(): ?string {
    if ($uri = getenv('ACLI_CLOUD_API_ACCOUNTS_URI')) {
      return $uri;
    }
    return NULL;
  }

}
