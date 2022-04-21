<?php

namespace Acquia\Cli\DataStore;

use Dflydev\DotAccessData\Data;
use Grasmash\Expander\Expander;
use Grasmash\Expander\Stringifier;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class YamlStore
 * @package Acquia\Cli\DataStore
 */
abstract class Datastore implements DataStoreInterface {

  /** @var Data */
  protected $data;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fileSystem;

  /**
   * @var string
   */
  protected $filepath;

  /**
   * @var \Grasmash\Expander\Expander
   */
  protected $expander;

  /**
   * @param string $path
   */
  public function __construct(string $path) {
    $this->fileSystem = new Filesystem();
    $this->filepath = $path;
    $this->expander = new Expander();
    $this->expander->setStringifier(new Stringifier());
    $this->data = new Data();
  }

  /**
   * @param string $key
   * @param mixed $value
   */
  public function set(string $key, $value) {
    $this->data->set($key, $value);
    $this->dump();
  }

  /**
   * @param string $key
   * @param null $default
   *
   * @return array|mixed|null
   */
  public function get(string $key, $default = NULL) {
    return $this->data->get($key);
  }

  /**
   * @param string $key
   */
  public function remove(string $key) {
    $this->data->remove($key);
  }

  /**
   * @param string $key
   *
   * @return bool
   */
  public function exists(string $key) {
    return $this->data->has($key);
  }

  /**
   * @param array $config
   * @param \Symfony\Component\Config\Definition\ConfigurationInterface $definition
   *
   * @return array
   */
  protected function processConfig(array $config, ConfigurationInterface $definition): array {
    $processor = new Processor();
    return $processor->processConfiguration(
      $definition,
      [$definition->getName() => $config],
    );
  }

}