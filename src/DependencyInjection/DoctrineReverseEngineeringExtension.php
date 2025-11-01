<?php

declare(strict_types=1);

namespace DoctrineReverseEngineering\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class DoctrineReverseEngineeringExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('doctrine_reverse_engineering.entity_path', $config['entity_path']);
        $container->setParameter('doctrine_reverse_engineering.entity_namespace', $config['entity_namespace']);
        $container->setParameter('doctrine_reverse_engineering.repository_path', $config['repository_path']);
        $container->setParameter('doctrine_reverse_engineering.repository_namespace', $config['repository_namespace']);
        $container->setParameter('doctrine_reverse_engineering.generate_repositories', $config['generate_repositories']);
        $container->setParameter('doctrine_reverse_engineering.overwrite_existing', $config['overwrite_existing']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');
    }
}
