<?php

declare(strict_types=1);

namespace Acquia\Cli\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class CloudDataConfig implements ConfigurationInterface
{
    public function getName(): string
    {
        return 'cloud_api';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cloud_api');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()

            // I can't find a better node type that accepts TRUE, FALSE, and NULL.
            // boolNode() will cast NULL to FALSE and enumNode()->values() will
            // strip out a NULL value.
            ->scalarNode('send_telemetry')->end()
            ->scalarNode('acli_key')->end()
            ->arrayNode('keys')
            ->useAttributeAsKey('uuid')
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->children()
            ->scalarNode('label')->end()
            ->scalarNode('uuid')->end()
            ->scalarNode('secret')->isRequired()->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('user')
            ->children()
            ->scalarNode('uuid')->end()
            ->booleanNode('is_acquian')
            ->defaultValue(false)
            ->end()
            ->end()
            ->end()
            ->arrayNode('acsf_factories')
            ->useAttributeAsKey('url')
            ->normalizeKeys(false)
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
            ->end()
            ->validate()
            ->ifTrue(function ($config) {
                return is_array($config['keys']) && !empty($config['keys']) && !array_key_exists($config['acli_key'], $config['keys']);
            })
            ->thenInvalid('acli_key must exist in keys');
        return $treeBuilder;
    }
}
