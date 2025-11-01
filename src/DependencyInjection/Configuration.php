<?php

declare(strict_types=1);

namespace DoctrineReverseEngineering\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('doctrine_reverse_engineering');

        $rootNode = $treeBuilder->getRootNode();
        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new \LogicException('Root node must be an array node.');
        }

        $children = $rootNode->children();

        $children
            ->scalarNode('entity_path')
            ->defaultValue('%kernel.project_dir%/src/Entity')
            ->end()
        ;

        $children
            ->scalarNode('entity_namespace')
            ->defaultValue('App\Entity')
            ->end()
        ;

        $children
            ->scalarNode('repository_path')
            ->defaultValue('%kernel.project_dir%/src/Repository')
            ->end()
        ;

        $children
            ->scalarNode('repository_namespace')
            ->defaultValue('App\Repository')
            ->end()
        ;

        $children
            ->booleanNode('generate_repositories')
            ->defaultTrue()
            ->end()
        ;

        $children
            ->booleanNode('overwrite_existing')
            ->defaultFalse()
            ->end()
        ;

        return $treeBuilder;
    }
}
