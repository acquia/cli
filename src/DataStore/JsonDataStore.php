<?php

namespace Acquia\Cli\DataStore;

use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class YamlStore
 *
 * @package Acquia\Cli\DataStore
 */
class JsonDataStore extends Datastore implements DataStoreInterface {

  /**
   * Creates a new store.
   *
   * @param string $path
   * @param \Symfony\Component\Config\Definition\ConfigurationInterface|null $config_definition
   */
  public function __construct(string $path, ConfigurationInterface $config_definition = NULL) {
    parent::__construct($path);
    if ($this->fileSystem->exists($path)) {
      $array = json_decode(file_get_contents($path), TRUE);
      $array = $this->expander->expandArrayProperties($array);
      if ($config_definition) {
        $array = $this->processConfig($array, $config_definition);
      }
      $this->data->import($array);
    }
  }

  /**
   *
   */
  public function dump() {
    $this->fileSystem->dumpFile($this->filepath, json_encode($this->data->export(), JSON_PRETTY_PRINT));
  }
}