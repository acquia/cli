<?php

namespace Acquia\Cli\DataStore;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Yaml\Yaml;

class YamlStore extends Datastore {

  /**
   * Creates a new store.
   *
   * @param \Symfony\Component\Config\Definition\ConfigurationInterface|null $configDefinition
   */
  public function __construct(string $path, ConfigurationInterface $configDefinition = NULL) {
    parent::__construct($path);
    if ($this->fileSystem->exists($path)) {
      $array = Yaml::parseFile($path);
      $array = $this->expander->expandArrayProperties($array);
      if ($configDefinition) {
        $array = $this->processConfig($array, $configDefinition, $path);
      }
      $this->data->import($array);
    }
  }

  public function dump(): void {
    $this->fileSystem->dumpFile($this->filepath, Yaml::dump($this->data->export()));
  }

}
