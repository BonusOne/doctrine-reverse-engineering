<?php

declare(strict_types=1);

namespace DoctrineReverseEngineering\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('doctrine_reverse_engineering');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('entity_path')
            ->defaultValue('%kernel.project_dir%/src/Entity')
            ->end()
            ->scalarNode('entity_namespace')
            ->defaultValue('App\Entity')
            ->end()
            ->scalarNode('repository_path')
            ->defaultValue('%kernel.project_dir%/src/Repository')
            ->end()
            ->scalarNode('repository_namespace')
            ->defaultValue('App\Repository')
            ->end()
            ->booleanNode('generate_repositories')
            ->defaultTrue()
            ->end()
            ->booleanNode('overwrite_existing')
            ->defaultFalse()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
