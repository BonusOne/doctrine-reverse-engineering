# Doctrine Reverse Engineering

Doctrine entity and repository generator that reverse engineers an existing database for Symfony 6/7 projects.

## Requirements

- PHP 8.2 or newer
- Symfony FrameworkBundle 6.4/7.x
- Doctrine DBAL 3.8+
- A valid `DATABASE_URL` connection string

## Installation

```bash
composer require bonusone/doctrine-reverse-engineering
```

The bundle registers itself automatically thanks to the `symfony.bundle` section in `composer.json`.

## Optional Configuration

Override default paths or behaviour by adding `config/packages/doctrine_reverse_engineering.yaml` in your application:

```yaml
doctrine_reverse_engineering:
    entity_path: '%kernel.project_dir%/src/Entity'
    entity_namespace: 'App\Entity'
    repository_path: '%kernel.project_dir%/src/Repository'
    repository_namespace: 'App\Repository'
    generate_repositories: true
    overwrite_existing: false
```

## Usage

Generate all tables without overwriting existing files:

```bash
php bin/console doctrine:reverse-engineering:generate
```

Available options:

| Option                                         | Description |
|------------------------------------------------| ----------- |
| `--connection`                                 | Doctrine connection name (defaults to `default`) |
| `--entity-path` / `--entity-namespace`         | Target path and base namespace for entities |
| `--repository-path` / `--repository-namespace` | Target path and base namespace for repositories |
| `--repositories=auto/yes/no`                   | Force repository generation on/off or reuse configuration |
| `--overwrite` / `--no-overwrite`               | Control whether existing files are replaced |
| `--table=foo --table=bar`                      | Limit generation to selected tables |
| `--dry-run`                                    | Preview the output without writing files |

Shortcut wrapper available via `vendor/bin`:

```bash
vendor/bin/doctrine-reverse-engineering --table=users --overwrite
```

## License

Released under the MIT License (see `LICENSE`).
