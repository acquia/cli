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
  protected $datastore;

  /**
   * @return KeyValueStore
   */
  public function getCloudApiDatastore() {
    return $this->datastore;
  }

  /**
   * @param KeyValueStore $datastore
   */
  public function setCloudApiDatastore(KeyValueStore $datastore): void {
    $this->datastore = $datastore;
  }

}
