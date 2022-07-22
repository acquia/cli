<?php

namespace Acquia\Cli\Helpers;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class AliasCache extends FilesystemAdapter {

  /**
   * {@inheritdoc}
   */
  public function get(string $key, callable $callback, float $beta = NULL, array &$metadata = NULL): mixed {
    // Aliases format is `realm:name.env`, but `:` is not a legal character.
    $key = str_replace(':', '.', $key);
    return parent::get($key, $callback, $beta, $metadata);
  }

}
