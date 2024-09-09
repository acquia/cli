<?php

declare(strict_types=1);

namespace Acquia\Cli\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class AcquiaCliConfig implements ConfigurationInterface
{
    public function getName(): string
    {
        return 'acquia_cli';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acquia_cli');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->scalarNode('cloud_app_uuid')->end()
                    ->arrayNode('push')
                        ->children()
                            ->arrayNode('artifact')
                                ->children()
                                    ->arrayNode('destination_git_urls')
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
        return $treeBuilder;
    }
}
