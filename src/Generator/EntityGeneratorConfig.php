<?php

declare(strict_types=1);

namespace DoctrineReverseEngineering\Generator;

/**
 * @psalm-immutable
 */
final readonly class EntityGeneratorConfig
{
    /**
     * @param string[] $tables
     */
    public function __construct(
        public string $entityNamespace,
        public string $entityPath,
        public string $repositoryNamespace,
        public string $repositoryPath,
        public bool $generateRepositories,
        public bool $overwriteExisting,
        public bool $dryRun,
        public array $tables = [],
    ) {
    }
}
