<?php

namespace Acquia\Cli\DataStore;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Webmozart\KeyValueStore\ArrayStore;

/**
 * Class YamlStore
 * @package Acquia\Cli\DataStore
 */
class YamlStore extends ArrayStore
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
   * Creates a new store.
   *
   * @param string $path
   * @param int $flags
   */
  public function __construct($path, $flags = 0) {
    $this->fileSystem = new Filesystem();
    $this->filepath = $path;

    if ($this->fileSystem->exists($path)) {
      $array = Yaml::parseFile($path);
      parent::__construct($array, $flags);
    }
    else {
      parent::__construct([], $flags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    parent::set($key, $value);
    $this->fileSystem->dumpFile($this->filepath, Yaml::dump($this->toArray()));
  }

}
