<?php

namespace Acquia\Cli\DataStore;

/**
 * Interface DataStoreAwareInterface.
 *
 * @package Acquia\Cli\DataStore
 */
interface DataStoreAwareInterface {

  /***
   * Inject a data store object.
   *
   * @param DataStoreInterface $data_store
   */
  public function setDataStore(DataStoreInterface $data_store);

  /**
   * Get the data store object.
   *
   * @return DataStoreInterface
   */
  public function getDataStore(): DataStoreInterface;

}
