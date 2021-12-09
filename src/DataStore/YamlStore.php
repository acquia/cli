<?php

namespace Acquia\Cli\DataStore;

use Dflydev\DotAccessData\Data;
use Grasmash\Expander\Expander;
use Grasmash\Expander\Stringifier;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlStore
 * @package Acquia\Cli\DataStore
 */
class YamlStore
{

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private $fileSystem;

  /**
   * @var string
   */
  private $filepath;

  /**
   * @var \Grasmash\Expander\Expander
   */
  private $expander;

  /**
   * @var \Dflydev\DotAccessData\Data
   */
  private $data;

  /**
   * Creates a new store.
   *
   * @param string $path
   */
  public function __construct(string $path) {
    $this->fileSystem = new Filesystem();
    $this->filepath = $path;
    $this->expander = new Expander();
    $this->expander->setStringifier(new Stringifier());
    $this->data = new Data();
    if ($this->fileSystem->exists($path)) {
      $array = Yaml::parseFile($path);
      $array = $this->expander->expandArrayProperties($array);
      $this->data->import($array);
    }
  }

  /**
   * @param string $key
   * @param $value
   */
  public function set(string $key, $value) {
    $this->data->set($key, $value);
    $this->dump();
  }

  public function get($key) {
    return $this->data->get($key);
  }

  private function dump() {
    $this->fileSystem->dumpFile($this->filepath, Yaml::dump($this->data->export()));
  }

}
