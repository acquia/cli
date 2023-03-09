<?php

namespace Acquia\Cli\DataStore;

interface DataStoreInterface {

  public function set(string $key, $value): void;

  public function get(string $key): mixed;

  public function dump(): void;

  public function remove(string $key): void;

  public function exists(string $key): bool;

}
