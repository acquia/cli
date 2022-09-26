<?php

namespace Acquia\Cli\DataStore;

use Dflydev\DotAccessData\Data;
use Dflydev\DotAccessData\Exception\MissingPathException;
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

  protected Data $data;

  protected Filesystem $fileSystem;

  public string $filepath;

  protected Expander $expander;

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
  public function set(string $key, $value): void {
    $this->data->set($key, $value);
    $this->dump();
  }

  /**
   * @param string $key
   * @param null $default
   *
   * @return array|mixed|null
   */
  public function get(string $key, $default = NULL): mixed {
    try {
      return $this->data->get($key);
    }
    catch (MissingPathException) {
      return NULL;
    }
  }

  /**
   * @param string $key
   */
  public function remove(string $key): void {
    $this->data->remove($key);
    $this->dump();
  }

  /**
   * @param string $key
   *
   * @return bool
   */
  public function exists(string $key): bool {
    return $this->data->has($key);
  }

  /**
   * @param array $config
   * @param \Symfony\Component\Config\Definition\ConfigurationInterface $definition
   *
   * @return array
   */
  protected function processConfig(array $config, ConfigurationInterface $definition): array {
    return (new Processor())->processConfiguration(
      $definition,
      [$definition->getName() => $config],
    );
  }

}
