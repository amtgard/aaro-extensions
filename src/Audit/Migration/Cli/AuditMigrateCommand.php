<?php

namespace Amtgard\AaroExtensions\Audit\Migration\Cli;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\Migration\AuditMigrationGeneratorInterface;
use Amtgard\AaroExtensions\Audit\Migration\AuditMigrationService;
use Amtgard\AaroExtensions\Audit\Migration\AuditTableExclusionsLoader;
use Amtgard\AaroExtensions\Audit\Migration\Schema\DatabaseSchemaSource;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Dotenv\Dotenv;

final class AuditMigrateCommand
{
    public function __construct(
        private readonly ?AuditMigrationGeneratorInterface $migrationGenerator = null,
    ) {
    }

    /**
     * @param string[] $argv
     */
    public function run(array $argv): int
    {
        $args = $this->parseArguments($argv);

        if ($args['help'] || $args['command'] === null) {
            $this->printHelp();
            return $args['help'] ? 0 : 1;
        }

        if (empty($args['env'])) {
            fwrite(STDERR, "Error: --env is required.\n");
            return 1;
        }

        if (empty($args['out-dir'])) {
            fwrite(STDERR, "Error: --out-dir is required.\n");
            return 1;
        }

        $service = $this->migrationGenerator ?? new AuditMigrationService(
            new DatabaseSchemaSource(
                $this->connectDatabase($args['env']),
                AuditTableExclusionsLoader::load(
                    AuditTableExclusionsLoader::defaultPath(),
                    $args['exclude-file'] ?: null,
                ),
            ),
        );
        $configuration = AuditConfiguration::builder()->build();
        $table = $args['table'] ?: null;

        try {
            $files = match ($args['command']) {
                'phinx' => $service->generateCreateMigrations($args['out-dir'], $table, $configuration),
                'patch' => $service->generatePatchMigrations($args['out-dir'], $table, $configuration),
                default => throw new \InvalidArgumentException("Unknown command: {$args['command']}"),
            };
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        if ($files === []) {
            echo "No changes required.\n";
            return 0;
        }

        foreach ($files as $file) {
            echo "Generated: $file\n";
        }

        return 0;
    }

    private function connectDatabase(string $envPath): Database
    {
        $envFile = $this->normalizeEnvPath($envPath);
        if (!file_exists($envFile)) {
            throw new \RuntimeException("Environment file not found: $envFile");
        }

        $dotenv = Dotenv::createImmutable(dirname($envFile), basename($envFile));
        $dotenv->safeLoad();

        $config = DatabaseConfiguration::fromEnvironment();
        $provider = MysqlPdoProvider::fromConfiguration($config);

        return Database::fromProvider($provider);
    }

    private function normalizeEnvPath(string $path): string
    {
        $path = rtrim($path, '/\\');

        return is_dir($path) ? $path . DIRECTORY_SEPARATOR . '.env' : $path;
    }

    /**
     * @param string[] $argv
     * @return array{command: ?string, env: ?string, table: ?string, out-dir: ?string, exclude-file: ?string, help: bool}
     */
    public function parseArguments(array $argv): array
    {
        $args = [
            'command' => null,
            'env' => null,
            'table' => null,
            'out-dir' => null,
            'exclude-file' => null,
            'help' => false,
        ];

        $pendingKey = null;
        for ($index = 1; $index < count($argv); $index++) {
            $arg = $argv[$index];

            if ($arg === '--help' || $arg === '-h') {
                $args['help'] = true;
                continue;
            }

            if (str_starts_with($arg, '--')) {
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', substr($arg, 2), 2);
                    $args[$key] = $value;
                    $pendingKey = null;
                    continue;
                }

                $pendingKey = substr($arg, 2);
                continue;
            }

            if ($pendingKey !== null) {
                $args[$pendingKey] = $arg;
                $pendingKey = null;
                continue;
            }

            if ($args['command'] === null) {
                $args['command'] = $arg;
            }
        }

        return $args;
    }

    private function printHelp(): void
    {
        echo <<<HELP
aaro-audit-migrate — generate Phinx migrations for audit tables

Usage:
  aaro-audit-migrate phinx --env=<path> --out-dir=<path> [--table=<table>] [--exclude-file=<path>]
  aaro-audit-migrate patch --env=<path> --out-dir=<path> [--table=<table>] [--exclude-file=<path>]

Commands:
  phinx   Create audit table migration(s) from the live core table schema
  patch   Patch audit table migration(s) after core schema changes

Options:
  --env       Path to .env file or directory containing .env (required)
  --out-dir   Directory for generated migration files (required)
  --table         Core table name (optional; all non-excluded tables if omitted)
  --exclude-file  Additional YAML exclusions merged with the bundled defaults
  --help          Show this help message

Examples:
  aaro-audit-migrate phinx --env=./.env --out-dir=./db/migrations --table=users
  aaro-audit-migrate patch --env=./.env --out-dir=./db/migrations

HELP;
    }
}
