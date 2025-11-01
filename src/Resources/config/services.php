<?php

declare(strict_types=1);

use DoctrineReverseEngineering\Command\GenerateEntitiesCommand;
use DoctrineReverseEngineering\Generator\EntityGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private()
    ;

    $services->set(EntityGenerator::class);

    $services->set(GenerateEntitiesCommand::class)
        ->tag('console.command')
    ;
};
