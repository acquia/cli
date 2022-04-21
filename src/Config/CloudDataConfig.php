<?php

namespace Acquia\Cli\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class CloudDataConfig implements ConfigurationInterface {

  /**
   * @return string
   */
  public function getName(): string {
    return 'cloud_api';
  }

  /**
   * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
   */
  public function getConfigTreeBuilder(): TreeBuilder {
    $treeBuilder = new TreeBuilder('cloud_api');
    $root_node = $treeBuilder->getRootNode();
    $root_node
      ->children()

        ->booleanNode('send_telemetry')
          ->defaultNull()
        ->end()

        ->scalarNode('acli_key')->end()

        ->arrayNode('keys')
            ->useAttributeAsKey('uuid')
            ->arrayPrototype()
                ->children()
                  ->scalarNode('label')->end()
                  ->scalarNode('uuid')->end()
                  ->scalarNode('secret')->end()
                ->end()
            ->end()
        ->end()

        ->arrayNode('user')
            ->children()
                ->scalarNode('uuid')->end()
                ->booleanNode('is_acquian')
                  ->defaultValue(FALSE)
                ->end()
            ->end()
        ->end()

        ->arrayNode('acsf_factories')
            ->useAttributeAsKey('url')
            ->arrayPrototype()
                ->children()
                    ->arrayNode('users')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('username')->end()
                                ->scalarNode('password')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->scalarNode('url')->end()
                    ->scalarNode('active_user')->end()
                ->end()
            ->end()
        ->end()

        ->scalarNode('acsf_active_factory')->end()

      ->end();
    return $treeBuilder;
  }
}