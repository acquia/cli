<?php

namespace Acquia\Cli\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class AcquiaCliConfig implements ConfigurationInterface {

  public function getName(): string {
    return 'acquia_cli';
  }

  public function getConfigTreeBuilder(): TreeBuilder {
    $treeBuilder = new TreeBuilder('acquia_cli');
    $treeBuilder
      ->getRootNode()
        ->children()
          ->scalarNode('cloud_app_uuid')
        ->end()
      ->end();
    return $treeBuilder;
  }

}
