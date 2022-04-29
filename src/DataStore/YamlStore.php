<?php

namespace Acquia\Cli\DataStore;

use Dflydev\DotAccessData\Data;
use Grasmash\Expander\Expander;
use Grasmash\Expander\Stringifier;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Filesystem\Filesystem;
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
        $array = $this->processConfig($array, $config_definition);
      }
      $this->data->import($array);
    }
  }

  /**
   *
   */
  public function dump() {
    $this->fileSystem->dumpFile($this->filepath, Yaml::dump($this->data->export()));
  }

}
