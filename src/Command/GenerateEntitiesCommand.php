<?php

declare(strict_types=1);

namespace DoctrineReverseEngineering\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineReverseEngineering\Generator\EntityGenerator;
use DoctrineReverseEngineering\Generator\EntityGeneratorConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'doctrine:reverse-engineering:generate',
    description: 'Generates Doctrine entities and repositories based on an existing database.'
)]
final class GenerateEntitiesCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly EntityGenerator $generator,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%doctrine_reverse_engineering.entity_path%')] private readonly string $defaultEntityPath,
        #[Autowire('%doctrine_reverse_engineering.entity_namespace%')] private readonly string $defaultEntityNamespace,
        #[Autowire('%doctrine_reverse_engineering.repository_path%')] private readonly string $defaultRepositoryPath,
        #[Autowire('%doctrine_reverse_engineering.repository_namespace%')] private readonly string $defaultRepositoryNamespace,
        #[Autowire('%doctrine_reverse_engineering.generate_repositories%')] private readonly bool $defaultGenerateRepositories,
        #[Autowire('%doctrine_reverse_engineering.overwrite_existing%')] private readonly bool $defaultOverwriteExisting,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Doctrine DBAL/ORM connection name.', 'default')
            ->addOption('entity-path', null, InputOption::VALUE_REQUIRED, 'Target entity path (relative to the project directory).', $this->defaultEntityPath)
            ->addOption('entity-namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace for generated entities.', $this->defaultEntityNamespace)
            ->addOption('repository-path', null, InputOption::VALUE_REQUIRED, 'Target repository path.', $this->defaultRepositoryPath)
            ->addOption('repository-namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace for generated repositories.', $this->defaultRepositoryNamespace)
            ->addOption('repositories', null, InputOption::VALUE_REQUIRED, 'Controls repository generation (auto|yes|no).', 'auto')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Force overwriting existing files.')
            ->addOption('no-overwrite', null, InputOption::VALUE_NONE, 'Never overwrite existing files.')
            ->addOption('table', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limit generation to the selected tables.')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Display the plan without writing any files.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $connectionName = (string) $input->getOption('connection');

        try {
            $connection = $this->registry->getConnection($connectionName);
        } catch (\Throwable $throwable) {
            $io->error(\sprintf(
                'Failed to retrieve Doctrine connection "%s": %s',
                $connectionName,
                $throwable->getMessage()
            ));

            return Command::FAILURE;
        }

        if (!$connection instanceof Connection) {
            $io->error(\sprintf('Connection "%s" is not an instance of Doctrine\DBAL\Connection.', $connectionName));

            return Command::FAILURE;
        }

        $entityNamespace = (string) $input->getOption('entity-namespace');
        $repositoryNamespace = (string) $input->getOption('repository-namespace');
        $entityPath = $this->toAbsolutePath((string) $input->getOption('entity-path'));
        $repositoryPath = $this->toAbsolutePath((string) $input->getOption('repository-path'));

        $repositoriesOption = strtolower((string) $input->getOption('repositories'));
        $generateRepositories = match ($repositoriesOption) {
            'yes', 'true', '1' => true,
            'no', 'false', '0' => false,
            'auto' => $this->defaultGenerateRepositories,
            default => throw new \InvalidArgumentException('Allowed values for --repositories are: auto, yes, no.'),
        };

        $overwrite = $this->defaultOverwriteExisting;
        if ($input->getOption('overwrite')) {
            $overwrite = true;
        }

        if ($input->getOption('no-overwrite')) {
            $overwrite = false;
        }

        if ($input->getOption('overwrite') && $input->getOption('no-overwrite')) {
            throw new \InvalidArgumentException('Do not combine --overwrite with --no-overwrite in one execution.');
        }

        $tables = array_values(array_filter(
            array_map('trim', (array) $input->getOption('table')),
            static fn (?string $value): bool => $value !== null && $value !== ''
        ));

        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Doctrine reverse engineering');
        $io->listing([
            'Connection: '.$connectionName,
            'Repository generation: '.($generateRepositories ? 'yes' : 'no'),
            'Overwrite files: '.($overwrite ? 'yes' : 'no'),
            'Dry-run: '.($dryRun ? 'yes' : 'no'),
            'Entity path: '.$entityPath,
            'Repository path: '.$repositoryPath,
        ]);
        if ($tables !== []) {
            $io->text('Tables to process: '.implode(', ', $tables));
        } else {
            $io->text('Tables to process: all available tables.');
        }

        $io->newLine();

        try {
            $result = $this->generator->generate(
                $connection,
                new EntityGeneratorConfig(
                    $entityNamespace,
                    $entityPath,
                    $repositoryNamespace,
                    $repositoryPath,
                    $generateRepositories,
                    $overwrite,
                    $dryRun,
                    $tables
                )
            );
        } catch (DBALException|\Throwable $exception) {
            $io->error('Generation failed: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Dry-run mode: no files were created or modified.');
        }

        if ($result['entities'] !== []) {
            $io->section('Entities');
            foreach ($result['entities'] as $table => $file) {
                $io->writeln(\sprintf(' - %s -> %s', $table, $file));
            }
        }

        if ($result['repositories'] !== []) {
            $io->section('Repositories');
            foreach ($result['repositories'] as $table => $file) {
                $io->writeln(\sprintf(' - %s -> %s', $table, $file));
            }
        }

        if ($result['skipped'] !== []) {
            $io->warning([
                'Skipped files:',
                ...array_map(static fn (string $file): string => ' - '.$file, $result['skipped']),
                'Use --overwrite to replace them or delete them manually before running again.',
            ]);
        }

        if ($result['errors'] !== []) {
            $io->error(array_merge(['Errors detected:'], $result['errors']));

            return Command::FAILURE;
        }

        $io->success('Generation completed successfully.');

        return Command::SUCCESS;
    }

    private function toAbsolutePath(string $path): string
    {
        if ($path === '') {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        return Path::isAbsolute($path)
            ? Path::normalize($path)
            : Path::normalize($this->projectDir.\DIRECTORY_SEPARATOR.$path);
    }
}
