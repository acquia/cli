<?php

namespace Acquia\Cli\DataStore;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlStore
 * @package Acquia\Cli\DataStore
 */
class YamlStore extends Datastore {

  /**
   * Creates a new store.
   *
   * @param string $path
   * @param \Symfony\Component\Config\Definition\ConfigurationInterface|null $config_definition
   */
  public function __construct(string $path, ConfigurationInterface $config_definition = NULL) {
    parent::__construct($path);
    if ($this->fileSystem->exists($path)) {
      $array = Yaml::parseFile($path);
      $array = $this->expander->expandArrayProperties($array);
      if ($config_definition) {
        $array = $this->processConfig($array, $config_definition, $path);
      }
      $this->data->import($array);
    }
  }

  /**
   *
   */
  public function dump(): void {
    $this->fileSystem->dumpFile($this->filepath, Yaml::dump($this->data->export()));
  }

}
