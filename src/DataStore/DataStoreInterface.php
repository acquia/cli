<?php

namespace Acquia\Cli\DataStore;

interface DataStoreInterface {

  public function set(string $key, $value);

  public function get(string $key, $default = NULL);

  public function dump();

  public function remove(string $key);

  public function exists(string $key);

}
