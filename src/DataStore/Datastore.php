<?php

declare(strict_types = 1);

namespace Acquia\Cli\DataStore;

use Acquia\Cli\Exception\AcquiaCliException;
use Dflydev\DotAccessData\Data;
use Dflydev\DotAccessData\Exception\MissingPathException;
use Grasmash\Expander\Expander;
use Grasmash\Expander\Stringifier;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Filesystem\Filesystem;

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

  public function set(string $key, mixed $value): void {
    $this->data->set($key, $value);
    $this->dump();
  }

  public function get(string $key): mixed {
    try {
      return $this->data->get($key);
    }
    catch (MissingPathException) {
      return NULL;
    }
  }

  public function remove(string $key): void {
    $this->data->remove($key);
    $this->dump();
  }

  public function exists(string $key): bool {
    return $this->data->has($key);
  }

  /**
   * @param array $config
   * @param string $path Path to the datastore on disk.
   * @return array<mixed>
   */
  protected function processConfig(array $config, ConfigurationInterface $definition, string $path): array {
    try {
      return (new Processor())->processConfiguration(
        $definition,
        [$definition->getName() => $config],
      );
    }
    catch (InvalidConfigurationException $e) {
      throw new AcquiaCliException("Configuration file at the following path contains invalid keys: $path. {$e->getMessage()}");
    }
  }

}
