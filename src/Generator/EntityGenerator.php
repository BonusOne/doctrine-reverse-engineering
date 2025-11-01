<?php

declare(strict_types=1);

namespace DoctrineReverseEngineering\Generator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

final readonly class EntityGenerator
{
    private const TYPE_MAPPINGS = [
        'enum' => 'string',
        'point' => 'binary',
        'polygon' => 'binary',
        'multipolygon' => 'binary',
    ];

    public function __construct(private Filesystem $filesystem)
    {
    }

    /**
     * @return array{
     *     entities: array<string,string>,
     *     repositories: array<string,string>,
     *     skipped: string[],
     *     errors: string[]
     * }
     */
    /**
     * @param callable(string):void|null $progressCallback
     */
    public function generate(Connection $connection, EntityGeneratorConfig $config, ?callable $progressCallback = null): array
    {
        $result = [
            'entities' => [],
            'repositories' => [],
            'skipped' => [],
            'errors' => [],
        ];

        $platform = $connection->getDatabasePlatform();
        foreach (self::TYPE_MAPPINGS as $databaseType => $doctrineType) {
            try {
                $platform->registerDoctrineTypeMapping($databaseType, $doctrineType);
            } catch (\Throwable) {
                // Type may already be registered; ignore silently.
            }
        }

        try {
            $schemaManager = $connection->createSchemaManager();
            $availableTables = $schemaManager->listTableNames();
        } catch (\Throwable $throwable) {
            $result['errors'][] = 'Failed to fetch table list: '.$throwable->getMessage();

            return $result;
        }

        $tables = $this->resolveTablesToProcess($config->tables, $availableTables, $result['errors']);

        foreach ($tables as $tableName) {
            if ($progressCallback !== null) {
                $progressCallback($tableName);
            }

            try {
                $columns = $schemaManager->listTableColumns($tableName);
                $indexes = $schemaManager->listTableIndexes($tableName);
                $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
            } catch (\Throwable $exception) {
                $result['errors'][] = \sprintf("Failed to fetch metadata for table '%s': ", $tableName).$exception->getMessage();
                continue;
            }

            $naming = $this->resolveEntityNaming($tableName);
            $entityNamespace = $this->buildNamespace($config->entityNamespace, $naming['namespaceSegment']);
            $repositoryNamespace = $this->buildNamespace($config->repositoryNamespace, $naming['namespaceSegment']);

            $entityCode = $this->generateEntityCode(
                $tableName,
                $columns,
                $indexes,
                $foreignKeys,
                $naming,
                $entityNamespace,
                $config->entityNamespace,
                $repositoryNamespace,
                $config->generateRepositories
            );

            $entityDir = $this->buildPath($config->entityPath, $naming['namespaceSegment']);
            $entityFile = $entityDir.\DIRECTORY_SEPARATOR.$naming['className'].'.php';

            if ($this->persistFile($entityDir, $entityFile, $entityCode, $config->overwriteExisting, $config->dryRun, $result)) {
                $result['entities'][$tableName] = $entityFile;
            }

            if (!$config->generateRepositories) {
                continue;
            }

            $repositoryCode = $this->generateRepositoryCode(
                $naming,
                $entityNamespace,
                $repositoryNamespace
            );

            $repositoryDir = $this->buildPath($config->repositoryPath, $naming['namespaceSegment']);
            $repositoryFile = $repositoryDir.\DIRECTORY_SEPARATOR.$naming['className'].'Repository.php';

            if ($this->persistFile($repositoryDir, $repositoryFile, $repositoryCode, $config->overwriteExisting, $config->dryRun, $result)) {
                $result['repositories'][$tableName] = $repositoryFile;
            }
        }

        return $result;
    }

    /**
     * @param Column[]                                                                       $columns
     * @param Index[]                                                                        $indexes
     * @param ForeignKeyConstraint[]                                                         $foreignKeys
     * @param array{className:string,namespaceSegment:string,namingStyle:string,words:array} $entityNaming
     */
    private function generateEntityCode(
        string $tableName,
        array $columns,
        array $indexes,
        array $foreignKeys,
        array $entityNaming,
        string $entityNamespace,
        string $entityNamespaceRoot,
        string $repositoryNamespace,
        bool $withRepository,
    ): string {
        $className = $entityNaming['className'];

        $code = "<?php\n\n";
        $code .= "declare(strict_types=1);\n\n";
        $code .= 'namespace '.$entityNamespace.";\n\n";

        if ($withRepository) {
            $code .= 'use '.$repositoryNamespace.'\\'.$className."Repository;\n";
        }

        $relatedImports = [];
        $foreignKeyColumns = [];
        $entityProperties = '';
        $methods = '';
        $usedPropertyNames = [];

        $columnMap = [];
        foreach ($columns as $column) {
            $columnMap[$column->getName()] = $column;
        }

        foreach ($foreignKeys as $foreignKey) {
            $foreignTable = $foreignKey->getForeignTableName();
            $foreignNaming = $this->resolveEntityNaming($foreignTable);
            $foreignClassName = $foreignNaming['className'];
            $foreignNamespace = $this->buildNamespace($entityNamespaceRoot, $foreignNaming['namespaceSegment']);
            $foreignFqcn = $foreignNamespace.'\\'.$foreignClassName;
            $relatedImports[$foreignFqcn] = $foreignFqcn;

            $localColumns = $foreignKey->getLocalColumns();
            $foreignColumns = $foreignKey->getForeignColumns();

            foreach ($localColumns as $index => $localColumn) {
                $referencedColumn = $foreignColumns[$index] ?? $foreignColumns[0] ?? 'id';
                $foreignKeyColumns[] = $localColumn;

                $columnNullable = true;
                if (isset($columnMap[$localColumn])) {
                    $columnNullable = !$columnMap[$localColumn]->getNotnull();
                }

                $basePropertyName = $this->normalizeForeignKeyPropertyName($localColumn);
                if ($basePropertyName === '') {
                    $basePropertyName = $localColumn;
                }

                $propertyName = $this->ensureUniquePropertyName($basePropertyName, $usedPropertyNames);

                $entityProperties .= "    #[ORM\\ManyToOne(targetEntity: {$foreignClassName}::class)]\n";
                $joinColumn = \sprintf("    #[ORM\\JoinColumn(name: '%s', referencedColumnName: '%s'", $localColumn, $referencedColumn);
                if (!$columnNullable) {
                    $joinColumn .= ', nullable: false';
                }

                $joinColumn .= ")]\n";
                $entityProperties .= $joinColumn;
                $entityProperties .= '    private '.($columnNullable ? '?' : '').$foreignClassName.' $'.$propertyName;
                if ($columnNullable) {
                    $entityProperties .= ' = null';
                }

                $entityProperties .= ";\n\n";

                $methods .= $this->generateGetterSetter($propertyName, $foreignClassName, $columnNullable);
            }
        }

        if ($relatedImports !== []) {
            ksort($relatedImports);
            foreach ($relatedImports as $import) {
                $code .= "use {$import};\n";
            }
        }

        $code .= "use Doctrine\\DBAL\\Types\\Types;\n";
        $code .= "use Doctrine\\ORM\\Mapping as ORM;\n\n";

        if ($withRepository) {
            $code .= '#[ORM\Entity(repositoryClass: '.$className."Repository::class)]\n";
        } else {
            $code .= "#[ORM\\Entity]\n";
        }

        foreach ($indexes as $index) {
            $columnsList = implode("', '", $index->getColumns());
            $indexName = $index->getName();
            if ($index->isUnique() && strtolower($indexName) !== 'primary') {
                $code .= "#[ORM\\UniqueConstraint(name: '{$indexName}', columns: ['{$columnsList}'])]\n";
            } elseif (strtolower($indexName) !== 'primary') {
                $code .= "#[ORM\\Index(name: '{$indexName}', columns: ['{$columnsList}'])]\n";
            }
        }

        $code .= "#[ORM\\Table(name: '{$tableName}')]\n";
        $code .= 'class '.$className."\n";
        $code .= "{\n";
        $code .= $entityProperties;

        foreach ($columns as $column) {
            $columnName = $column->getName();
            if (\in_array($columnName, $foreignKeyColumns, true)) {
                continue;
            }

            $length = $column->getLength();
            $nullable = !$column->getNotnull();
            $default = $column->getDefault();
            $precision = $column->getPrecision();
            $scale = $column->getScale();
            $propertyAutoincrement = $column->getAutoincrement();

            $basePropertyName = $this->normalizeColumnPropertyName($columnName);
            if ($basePropertyName === '') {
                $basePropertyName = $columnName;
            }

            $propertyName = $this->ensureUniquePropertyName($basePropertyName, $usedPropertyNames);

            if (strtolower($propertyName) === 'id') {
                $code .= "    #[ORM\\Id]\n";
            }

            if ($propertyAutoincrement) {
                $code .= "    #[ORM\\GeneratedValue]\n";
            }

            $code .= \sprintf("    #[ORM\\Column(name: '%s', type: ", $columnName).$this->convertDoctrineType($column->getType());
            if ($precision !== null && $precision > 0) {
                $code .= ', precision: '.$precision;
            }

            if ($scale !== null && $scale > 0) {
                $code .= ', scale: '.$scale;
            }

            if ($length !== null && $length > 0) {
                $code .= ', length: '.$length;
            }

            if ($nullable) {
                $code .= ', nullable: true';
            }

            if ($default !== null) {
                $code .= ", options: ['default' => '".addslashes((string) $default)."']";
            }

            $code .= ")]\n";

            $propertyType = $this->getPhpDocType($column->getType());
            $code .= '    private ';
            if ($nullable) {
                $code .= '?';
            }

            $code .= $propertyType.' $'.$propertyName;
            if ($nullable) {
                $code .= ' = null';
            }

            $code .= ";\n\n";

            $methods .= $this->generateGetterSetter($propertyName, $propertyType, $nullable);
        }

        $code .= $methods;

        return $code."}\n";
    }

    /**
     * @param array{className:string,namespaceSegment:string,namingStyle:string,words:array} $entityNaming
     */
    private function generateRepositoryCode(
        array $entityNaming,
        string $entityNamespace,
        string $repositoryNamespace,
    ): string {
        $className = $entityNaming['className'];

        $code = "<?php\n\n";
        $code .= "declare(strict_types=1);\n\n";
        $code .= 'namespace '.$repositoryNamespace.";\n\n";
        $code .= 'use '.$entityNamespace.'\\'.$className.";\n";
        $code .= "use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;\n";
        $code .= "use Doctrine\\Persistence\\ManagerRegistry;\n\n";
        $code .= "/**\n";
        $code .= " * @extends ServiceEntityRepository<{$className}>\n";
        $code .= " */\n";
        $code .= 'class '.$className."Repository extends ServiceEntityRepository\n";
        $code .= "{\n";
        $code .= "    public function __construct(ManagerRegistry \$registry)\n";
        $code .= "    {\n";
        $code .= '        parent::__construct($registry, '.$className."::class);\n";
        $code .= "    }\n";

        return $code."}\n";
    }

    /**
     * @param array{
     *     entities: array<string,string>,
     *     repositories: array<string,string>,
     *     skipped: string[],
     *     errors: string[]
     * } $result
     */
    private function persistFile(
        string $directory,
        string $file,
        string $contents,
        bool $overwrite,
        bool $dryRun,
        array &$result,
    ): bool {
        if (!$overwrite && $this->filesystem->exists($file)) {
            $result['skipped'][] = $file;

            return false;
        }

        if ($dryRun) {
            return true;
        }

        try {
            $this->filesystem->mkdir($directory);
        } catch (IOExceptionInterface $ioException) {
            $result['errors'][] = \sprintf("Failed to create directory '%s': ", $directory).$ioException->getMessage();

            return false;
        }

        try {
            $this->filesystem->dumpFile($file, $contents);
        } catch (IOExceptionInterface $ioException) {
            $result['errors'][] = \sprintf("Failed to write file '%s': ", $file).$ioException->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @param string[] $requested
     * @param string[] $available
     */
    private function resolveTablesToProcess(array $requested, array $available, array &$errors): array
    {
        if ($requested === []) {
            sort($available);

            return $available;
        }

        $map = [];
        foreach ($available as $table) {
            $map[strtolower($table)] = $table;
        }

        $resolved = [];
        foreach ($requested as $table) {
            $key = strtolower($table);
            if (!isset($map[$key])) {
                $errors[] = \sprintf("Table '%s' does not exist in the schema.", $table);
                continue;
            }

            $resolved[$map[$key]] = true;
        }

        $tables = array_keys($resolved);
        sort($tables);

        return $tables;
    }

    private function buildNamespace(string $base, string $segment): string
    {
        $base = trim($base, '\\');
        $segment = trim($segment, '\\');

        if ($segment === '') {
            return $base;
        }

        if ($base === '') {
            return $segment;
        }

        return $base.'\\'.$segment;
    }

    private function buildPath(string $base, string $segment): string
    {
        $base = rtrim($base, \DIRECTORY_SEPARATOR);

        if ($segment === '') {
            return $base;
        }

        return $base.\DIRECTORY_SEPARATOR.$segment;
    }

    private function generateGetterSetter(string $propertyName, string $propertyType, bool $nullable): string
    {
        $code = '    public function get'.ucfirst($propertyName).'(): ';
        if ($nullable) {
            $code .= '?';
        }

        $code .= ltrim($propertyType, '\\')."\n";
        $code .= "    {\n";
        $code .= '        return $this->'.$propertyName.";\n";
        $code .= "    }\n\n";

        if (strtolower($propertyName) === 'id') {
            return $code;
        }

        $code .= '    public function set'.ucfirst($propertyName).'(';
        if ($nullable) {
            $code .= '?';
        }

        $code .= ltrim($propertyType, '\\').' $'.$propertyName."): self\n";
        $code .= "    {\n";
        $code .= '        $this->'.$propertyName.' = $'.$propertyName.";\n\n";
        $code .= "        return \$this;\n";

        return $code."    }\n\n";
    }

    private function convertDoctrineType(Type $type): string
    {
        return match ($type::class) {
            \Doctrine\DBAL\Types\BigIntType::class => 'Types::BIGINT',
            \Doctrine\DBAL\Types\BinaryType::class => 'Types::BINARY',
            \Doctrine\DBAL\Types\BlobType::class => 'Types::BLOB',
            \Doctrine\DBAL\Types\BooleanType::class => 'Types::BOOLEAN',
            \Doctrine\DBAL\Types\DateImmutableType::class,
            \Doctrine\DBAL\Types\DateTimeImmutableType::class,
            \Doctrine\DBAL\Types\DateTimeTzImmutableType::class,
            \Doctrine\DBAL\Types\TimeImmutableType::class,
            \Doctrine\DBAL\Types\DateTimeType::class,
            \Doctrine\DBAL\Types\DateTimeTzType::class,
            \Doctrine\DBAL\Types\DateType::class,
            \Doctrine\DBAL\Types\TimeType::class => 'Types::DATETIME_IMMUTABLE',
            \Doctrine\DBAL\Types\DateIntervalType::class => 'Types::DATEINTERVAL',
            \Doctrine\DBAL\Types\DecimalType::class => 'Types::DECIMAL',
            \Doctrine\DBAL\Types\FloatType::class => 'Types::FLOAT',
            \Doctrine\DBAL\Types\GuidType::class => 'Types::GUID',
            \Doctrine\DBAL\Types\IntegerType::class => 'Types::INTEGER',
            \Doctrine\DBAL\Types\JsonType::class => 'Types::JSON',
            \Doctrine\DBAL\Types\SimpleArrayType::class => 'Types::SIMPLE_ARRAY',
            \Doctrine\DBAL\Types\SmallIntType::class => 'Types::SMALLINT',
            \Doctrine\DBAL\Types\TextType::class => 'Types::TEXT',
            default => 'Types::STRING',
        };
    }

    /**
     * @return array{className:string,namespaceSegment:string,namingStyle:string,words:array}
     */
    private function resolveEntityNaming(string $tableName): array
    {
        $words = $this->explodeNameIntoWords($tableName);
        if ($words === []) {
            $words = [strtolower($tableName)];
        }

        $className = $this->convertWordsToPascalCase($words);
        if ($className === '') {
            $className = ucfirst($tableName) ?: 'Entity';
        }

        $namespaceWords = \count($words) > 1 ? [$words[0]] : $words;
        $namespaceSegment = $this->convertWordsToPascalCase($namespaceWords);
        if ($namespaceSegment === '') {
            $namespaceSegment = 'Others';
        }

        return [
            'className' => $className,
            'namespaceSegment' => $namespaceSegment,
            'namingStyle' => $this->detectNamingStyle($tableName),
            'words' => $words,
        ];
    }

    private function detectNamingStyle(string $name): string
    {
        if ($name === '') {
            return 'unknown';
        }

        if (str_contains($name, '_')) {
            return 'snake_case';
        }

        if ($name === strtolower($name)) {
            return 'lowercase';
        }

        if ($name[0] === strtolower($name[0])) {
            return 'camelCase';
        }

        return 'PascalCase';
    }

    /**
     * @return string[]
     */
    private function explodeNameIntoWords(string $name): array
    {
        if ($name === '') {
            return [];
        }

        if (str_contains($name, '_')) {
            $parts = preg_split('/_+/', $name) ?: [];
            $parts = array_filter($parts, static fn ($part): bool => $part !== '');

            return array_map(static fn ($part) => strtolower($part), $parts);
        }

        if (preg_match_all('/[A-Z]?[a-z0-9]+|[A-Z]+(?![a-z0-9])/', $name, $matches) && $matches[0] !== []) {
            return array_map(static fn ($part) => strtolower($part), $matches[0]);
        }

        return [strtolower($name)];
    }

    /**
     * @param string[] $words
     */
    private function convertWordsToPascalCase(array $words): string
    {
        return implode('', array_map(static function ($word): string {
            $lower = strtolower($word);

            return $lower === '' ? '' : ucfirst($lower);
        }, $words));
    }

    /**
     * @param string[] $words
     */
    private function convertWordsToCamelCase(array $words): string
    {
        if ($words === []) {
            return '';
        }

        $words = array_map(static fn ($word) => strtolower($word), $words);
        $first = array_shift($words);
        $camel = (string) $first;

        foreach ($words as $word) {
            $camel .= ucfirst($word);
        }

        return $camel;
    }

    private function normalizeColumnPropertyName(string $columnName): string
    {
        $words = $this->explodeNameIntoWords($columnName);
        if ($words === []) {
            return $columnName;
        }

        $property = $this->convertWordsToCamelCase($words);

        return $property !== '' ? $property : $columnName;
    }

    private function normalizeForeignKeyPropertyName(string $columnName): string
    {
        $words = $this->explodeNameIntoWords($columnName);
        if ($words !== [] && strtolower((string) end($words)) === 'id') {
            array_pop($words);
        }

        if ($words === []) {
            $words = $this->explodeNameIntoWords($columnName);
        }

        $property = $this->convertWordsToCamelCase($words);

        return $property !== '' ? $property : $this->normalizeColumnPropertyName($columnName);
    }

    /**
     * @param array<string,bool> $usedPropertyNames
     */
    private function ensureUniquePropertyName(string $baseName, array &$usedPropertyNames): string
    {
        $candidate = $baseName !== '' ? $baseName : 'property';
        $unique = $candidate;
        $suffix = 1;

        while (isset($usedPropertyNames[$unique])) {
            ++$suffix;
            $unique = $candidate.$suffix;
        }

        $usedPropertyNames[$unique] = true;

        return $unique;
    }

    private function getPhpDocType(Type $type): string
    {
        return match ($type::class) {
            \Doctrine\DBAL\Types\BooleanType::class => 'bool',
            \Doctrine\DBAL\Types\DateImmutableType::class,
            \Doctrine\DBAL\Types\DateTimeImmutableType::class,
            \Doctrine\DBAL\Types\DateTimeTzImmutableType::class,
            \Doctrine\DBAL\Types\TimeImmutableType::class,
            \Doctrine\DBAL\Types\DateTimeType::class,
            \Doctrine\DBAL\Types\DateTimeTzType::class,
            \Doctrine\DBAL\Types\DateType::class,
            \Doctrine\DBAL\Types\TimeType::class => '\DateTimeImmutable',
            \Doctrine\DBAL\Types\DateIntervalType::class => '\DateInterval',
            \Doctrine\DBAL\Types\DecimalType::class,
            \Doctrine\DBAL\Types\FloatType::class => 'float',
            \Doctrine\DBAL\Types\BigIntType::class,
            \Doctrine\DBAL\Types\SmallIntType::class,
            \Doctrine\DBAL\Types\IntegerType::class => 'int',
            \Doctrine\DBAL\Types\SimpleArrayType::class,
            \Doctrine\DBAL\Types\JsonType::class => 'array',
            default => 'string',
        };
    }
}
