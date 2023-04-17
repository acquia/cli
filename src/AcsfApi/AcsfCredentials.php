<?php

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\DataStore\CloudDataStore;

class AcsfCredentials implements ApiCredentialsInterface {

  /**
   * CloudCredentials constructor.
   */
  public function __construct(private CloudDataStore $datastoreCloud) {
  }

  public function getCloudKey(): ?string {
    if (getenv('ACSF_USERNAME')) {
      return getenv('ACSF_USERNAME');
    }

    if (($current_factory = $this->getCurrentFactory()) && $active_user = $this->getFactoryActiveUser($current_factory)) {
      return $active_user['username'];
    }

    return NULL;
  }

  /**
   * @param array $factory
   */
  public function getFactoryActiveUser(array $factory): mixed {
    if (array_key_exists('active_user', $factory)) {
      $active_user = $factory['active_user'];
      if (array_key_exists($active_user, $factory['users'])) {
        return $factory['users'][$active_user];
      }
    }

    return NULL;
  }

  private function getCurrentFactory(): mixed {
    if (($factory = $this->datastoreCloud->get('acsf_active_factory')) && ($acsf_factories = $this->datastoreCloud->get('acsf_factories')) && array_key_exists($factory, $acsf_factories)) {
      return $acsf_factories[$factory];
    }
    return NULL;
  }

  public function getCloudSecret(): ?string {
    if (getenv('ACSF_KEY')) {
      return getenv('ACSF_KEY');
    }

    if (($current_factory = $this->getCurrentFactory()) && $active_user = $this->getFactoryActiveUser($current_factory)) {
      return $active_user['key'];
    }

    return NULL;
  }

  public function getBaseUri(): ?string {
    if (getenv('ACSF_FACTORY_URI')) {
      return getenv('ACSF_FACTORY_URI');
    }
    if ($factory = $this->datastoreCloud->get('acsf_active_factory')) {
      return $factory;
    }

    return NULL;
  }

}
