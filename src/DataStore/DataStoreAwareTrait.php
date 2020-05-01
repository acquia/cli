<?php

namespace Acquia\Ads\DataStore;

/**
 * Class DataStoreAwareTrait
 * @package Acquia\Ads\DataStore
 */
trait DataStoreAwareTrait
{
    /**
     * @var DataStoreInterface
     */
    protected $data_store;

    /**
     * @return mixed
     */
    public function getDataStore() {
        return $this->data_store;
    }

    /**
     * @param DataStoreInterface $data_store
     */
    public function setDataStore(DataStoreInterface $data_store): void {
        $this->data_store = $data_store;
    }

}
