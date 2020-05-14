<?php

namespace Acquia\Cli\Helpers;

use Webmozart\KeyValueStore\Api\KeyValueStore;

/**
 * Class DataStoreAwareTrait.
 *
 * @package Acquia\Cli\DataStore
 */
trait CloudApiDataStoreAwareTrait {
  /**
   * @var KeyValueStore
   */
  protected $cloudApiDatastore;

  /**
   * @return KeyValueStore
   */
  public function getCloudApiDatastore() {
    return $this->cloudApiDatastore;
  }

  /**
   * @param KeyValueStore $datastore
   */
  public function setCloudApiDatastore(KeyValueStore $datastore): void {
    $this->cloudApiDatastore = $datastore;
  }

}
