<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\Migration\Schema\SchemaSourceInterface;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;

final class AuditMigrationService implements AuditMigrationGeneratorInterface
{
    public function __construct(
        private readonly SchemaSourceInterface $schemaSource,
        private readonly AuditSchemaGenerator $generator = new AuditSchemaGenerator(),
        private readonly AuditSchemaDiffer $differ = new AuditSchemaDiffer(),
        private readonly AuditPhinxWriter $writer = new AuditPhinxWriter(),
    ) {
    }

    /**
     * @return string[] generated file paths
     */
    public function generateCreateMigrations(
        string $outputDirectory,
        ?string $tableName = null,
        ?AuditConfiguration $configuration = null,
    ): array {
        $tables = $this->resolveTables($tableName);
        $generated = [];

        if (count($tables) > 1) {
            $auditSchemas = [];
            foreach ($tables as $coreTable) {
                $auditSchemas[] = $this->buildCreateSchema($coreTable, $configuration);
            }

            $migrationFile = $this->migrationFilePath(
                $outputDirectory,
                $this->timestamp() . '_create_audit_tables',
            );
            $combined = $this->writer->writeCombinedCreateMigration($auditSchemas, $migrationFile);
            $this->writeFile($migrationFile, $combined);
            $generated[] = $migrationFile;

            return $generated;
        }

        $auditSchema = $this->buildCreateSchema($tables[0], $configuration);
        $migrationFile = $this->migrationFilePath(
            $outputDirectory,
            $this->timestamp() . '_create_' . $auditSchema->tableName,
        );
        $this->writeFile(
            $migrationFile,
            $this->writer->writeCreateMigration($auditSchema, $migrationFile),
        );
        $generated[] = $migrationFile;

        return $generated;
    }

    /**
     * @return string[] generated file paths
     */
    public function generatePatchMigrations(
        string $outputDirectory,
        ?string $tableName = null,
        ?AuditConfiguration $configuration = null,
    ): array {
        $tables = $this->resolveTables($tableName);
        $generated = [];
        $allOperations = [];
        $configuration ??= AuditConfiguration::builder()->build();

        foreach ($tables as $coreTable) {
            $auditTableName = $configuration->resolveAuditTableName($coreTable);

            if (!$this->schemaSource->tableExists($auditTableName)) {
                throw new \RuntimeException(
                    "Audit table '$auditTableName' does not exist. Run `aaro-audit-migrate phinx` first.",
                );
            }

            $coreSchema = $this->schemaSource->loadTableSchema($coreTable);
            $auditSchema = $this->schemaSource->loadTableSchema($auditTableName);
            $operations = $this->differ->diff($coreSchema, $auditSchema);

            if ($operations === []) {
                continue;
            }

            array_push($allOperations, ...$operations);
        }

        if ($allOperations === []) {
            return [];
        }

        $migrationFile = $this->migrationFilePath(
            $outputDirectory,
            $this->timestamp() . '_patch_audit_tables',
        );
        $this->writeFile(
            $migrationFile,
            $this->writer->writePatchMigration($allOperations, $migrationFile),
        );
        $generated[] = $migrationFile;

        return $generated;
    }

    private function buildCreateSchema(string $coreTable, ?AuditConfiguration $configuration): TableSchemaDefinition
    {
        $configuration ??= AuditConfiguration::builder()->build();
        $auditTableName = $configuration->resolveAuditTableName($coreTable);

        if ($this->schemaSource->tableExists($auditTableName)) {
            throw new \RuntimeException("Audit table '$auditTableName' already exists.");
        }

        $coreSchema = $this->schemaSource->loadTableSchema($coreTable);

        return $this->generator->generate($coreSchema, $configuration);
    }

    /**
     * @return string[]
     */
    private function resolveTables(?string $tableName): array
    {
        if ($tableName !== null && $tableName !== '') {
            return [$tableName];
        }

        $tables = $this->schemaSource->listCoreTables();
        if ($tables === []) {
            throw new \RuntimeException('No core tables found in database.');
        }

        return $tables;
    }

    private function migrationFilePath(string $outputDirectory, string $baseName): string
    {
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0755, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException("Could not create output directory: $outputDirectory");
        }

        return rtrim($outputDirectory, '/\\') . DIRECTORY_SEPARATOR . $baseName . '.php';
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Failed to write migration file: $path");
        }
    }

    private function timestamp(): string
    {
        return (new \DateTimeImmutable())->format('YmdHis');
    }
}
