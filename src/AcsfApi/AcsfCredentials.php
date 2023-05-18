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

    if (($currentFactory = $this->getCurrentFactory()) && $activeUser = $this->getFactoryActiveUser($currentFactory)) {
      return $activeUser['username'];
    }

    return NULL;
  }

  public function getFactoryActiveUser(array $factory): mixed {
    if (array_key_exists('active_user', $factory)) {
      $activeUser = $factory['active_user'];
      if (array_key_exists($activeUser, $factory['users'])) {
        return $factory['users'][$activeUser];
      }
    }

    return NULL;
  }

  private function getCurrentFactory(): mixed {
    if (($factory = $this->datastoreCloud->get('acsf_active_factory')) && ($acsfFactories = $this->datastoreCloud->get('acsf_factories')) && array_key_exists($factory, $acsfFactories)) {
      return $acsfFactories[$factory];
    }
    return NULL;
  }

  public function getCloudSecret(): ?string {
    if (getenv('ACSF_KEY')) {
      return getenv('ACSF_KEY');
    }

    if (($currentFactory = $this->getCurrentFactory()) && $activeUser = $this->getFactoryActiveUser($currentFactory)) {
      return $activeUser['key'];
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
