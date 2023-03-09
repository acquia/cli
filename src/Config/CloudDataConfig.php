<?php

namespace Acquia\Cli\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class CloudDataConfig implements ConfigurationInterface {

  public function getName(): string {
    return 'cloud_api';
  }

  public function getConfigTreeBuilder(): TreeBuilder {
    $treeBuilder = new TreeBuilder('cloud_api');
    $root_node = $treeBuilder->getRootNode();
    $root_node
      ->children()

        // I can't find a better node type that accepts TRUE, FALSE, and NULL.
        // boolNode() will cast NULL to FALSE and enumNode()->values() will
        // strip out a NULL value.
        ->scalarNode('send_telemetry')->end()

        ->scalarNode('acli_key')->end()

        ->arrayNode('keys')
            ->useAttributeAsKey('uuid')
            ->normalizeKeys(FALSE)
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
            ->normalizeKeys(FALSE)
            ->arrayPrototype()
                ->children()
                    ->arrayNode('users')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('username')->end()
                                ->scalarNode('key')->end()
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
