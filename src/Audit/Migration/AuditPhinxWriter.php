<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;

final class AuditPhinxWriter
{
    /**
     * @param TableSchemaDefinition[] $auditSchemas
     */
    public function writeCombinedCreateMigration(array $auditSchemas, string $migrationFile): string
    {
        $className = $this->classNameFromMigrationFile($migrationFile);
        $tableBlocks = array_map(
            fn (TableSchemaDefinition $schema) => $this->renderCreateTable($schema),
            $auditSchemas,
        );

        return <<<PHP
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class {$className} extends AbstractMigration
{
    public function change(): void
    {
{$this->indent(implode("\n\n", $tableBlocks))}
    }
}

PHP;
    }

    public function writeCreateMigration(
        TableSchemaDefinition $auditSchema,
        string $migrationFile,
    ): string {
        $className = $this->classNameFromMigrationFile($migrationFile);
        $tableCode = $this->renderCreateTable($auditSchema);

        return <<<PHP
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class {$className} extends AbstractMigration
{
    public function change(): void
    {
{$tableCode}
    }
}

PHP;
    }

    /**
     * @param PatchOperation[] $operations
     */
    public function writePatchMigration(array $operations, string $migrationFile): string
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('No patch operations to write.');
        }

        $className = $this->classNameFromMigrationFile($migrationFile);
        $lines = array_map(fn (PatchOperation $operation) => $this->renderPatchOperation($operation), $operations);

        return <<<PHP
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class {$className} extends AbstractMigration
{
    public function change(): void
    {
{$this->indent(implode("\n", $lines))}
    }
}

PHP;
    }

    public function renderCreateTable(TableSchemaDefinition $auditSchema): string
    {
        $lines = ['$this->table("' . $auditSchema->tableName . '")'];

        foreach ($auditSchema->columns as $column) {
            $lines[] = $this->renderAddColumn($column);
        }

        $lines[] = '->create();';

        return $this->indent(implode("\n            ", $lines));
    }

    private function renderAddColumn(ColumnDefinition $column): string
    {
        return sprintf(
            "->addColumn('%s', '%s', %s)",
            $column->name,
            $column->phinxType,
            $this->renderOptions($column->phinxOptions()),
        );
    }

    private function renderPatchOperation(PatchOperation $operation): string
    {
        return match ($operation->type) {
            PatchOperationType::AddColumn => sprintf(
                "\$this->table('%s')->addColumn('%s', '%s', %s)->update();",
                $operation->tableName,
                $operation->columnName,
                $operation->phinxType,
                $this->renderOptions($operation->options),
            ),
            PatchOperationType::ChangeColumn => sprintf(
                "\$this->table('%s')->changeColumn('%s', '%s', %s)->update();",
                $operation->tableName,
                $operation->columnName,
                $operation->phinxType,
                $this->renderOptions($operation->options),
            ),
            PatchOperationType::RenameColumn => sprintf(
                "\$this->table('%s')->renameColumn('%s', '%s')->update();",
                $operation->tableName,
                $operation->columnName,
                $operation->newColumnName,
            ),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function renderOptions(array $options): string
    {
        if ($options === []) {
            return '[]';
        }

        $parts = [];
        foreach ($options as $key => $value) {
            $parts[] = $this->renderOption($key, $value);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function renderOption(string $key, mixed $value): string
    {
        if ($key === 'values' && is_array($value)) {
            $values = implode(', ', array_map(
                static fn (string $item) => "'" . str_replace("'", "\\'", $item) . "'",
                $value,
            ));

            return "'values' => [$values]";
        }

        if (is_bool($value)) {
            return "'$key' => " . ($value ? 'true' : 'false');
        }

        if (is_string($value)) {
            return "'$key' => '" . str_replace("'", "\\'", $value) . "'";
        }

        return "'$key' => $value";
    }

    private function classNameFromMigrationFile(string $migrationFile): string
    {
        $baseName = basename($migrationFile, '.php');
        $parts = explode('_', $baseName, 2);
        $suffix = $parts[1] ?? $parts[0];

        return str_replace('_', '', ucwords($suffix, '_'));
    }

    private function indent(string $content): string
    {
        return '        ' . $content;
    }
}
