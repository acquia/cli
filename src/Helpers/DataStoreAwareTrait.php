<?php

namespace Acquia\Cli\Helpers;

use Webmozart\KeyValueStore\Api\KeyValueStore;

/**
 * Class DataStoreAwareTrait.
 *
 * @package Acquia\Cli\DataStore
 */
trait DataStoreAwareTrait {
  /**
   * @var KeyValueStore
   */
  protected $datastore;

  /**
   * @return mixed
   */
  public function getDatastore() {
    return $this->datastore;
  }

  /**
   * @param KeyValueStore $datastore
   */
  public function setDatastore(KeyValueStore $datastore): void {
    $this->datastore = $datastore;
  }

}
