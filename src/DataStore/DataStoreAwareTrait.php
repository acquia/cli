<?php

namespace Acquia\Cli\DataStore;

/**
 * Class DataStoreAwareTrait.
 *
 * @package Acquia\Cli\DataStore
 */
trait DataStoreAwareTrait {
  /**
   * @var DataStoreInterface
   */
  protected $datastore;

  /**
   * @return mixed
   */
  public function getDatastore() {
    return $this->datastore;
  }

  /**
   * @param DataStoreInterface $datastore
   */
  public function setDatastore(DataStoreInterface $datastore): void {
    $this->datastore = $datastore;
  }

}
