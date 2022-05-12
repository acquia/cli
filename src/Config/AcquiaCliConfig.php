<?php

namespace Acquia\Cli\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class AcquiaCliConfig implements ConfigurationInterface {

  /**
   * @return string
   */
  public function getName(): string {
    return 'acquia_cli';
  }

  /**
   * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
   */
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
