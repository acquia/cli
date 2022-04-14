<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ApiCredentialsInterface;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * @package Acquia\Cli\Helpers
 */
class AcsfCredentials implements ApiCredentialsInterface {

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

    if ($current_factory = $this->getCurrentFactory()) {
      if ($active_user = $this->getFactoryActiveUser($current_factory)) {
        return $active_user['username'];
      }
    }

    return NULL;
  }

  /**
   * @param array $factory
   *
   * @return mixed|null
   */
  public function getFactoryActiveUser(array $factory) {
    if (array_key_exists('active_user', $factory)) {
      $active_user = $factory['active_user'];
      if (array_key_exists($active_user, $factory['users'])) {
        return $factory['users'][$active_user];
      }
    }

    return NULL;
  }

  /**
   * @return mixed|null
   */
  public function getCurrentFactory() {
    if ($factory = $this->datastoreCloud->get('acsf_active_factory')) {
      if ($acsf_factories = $this->datastoreCloud->get('acsf_factories')) {
        if (array_key_exists($factory, $acsf_factories)) {
          return $acsf_factories[$factory];
        }
      }
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

    if ($current_factory = $this->getCurrentFactory()) {
      if ($active_user = $this->getFactoryActiveUser($current_factory)) {
        return $active_user['password'];
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
    if ($factory = $this->datastoreCloud->get('acsf_active_factory')) {
      return $factory;
    }

    return NULL;
  }

}
