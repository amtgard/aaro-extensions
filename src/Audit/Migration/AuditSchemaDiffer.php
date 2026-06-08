<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;

final class AuditSchemaDiffer
{
    private const DROPPED_COLUMN_PATTERN = '/^dropped_(\d+)_(.+)$/';

    public function __construct(private readonly AuditSchemaGenerator $generator = new AuditSchemaGenerator())
    {
    }

    /**
     * @return PatchOperation[]
     */
    public function diff(TableSchemaDefinition $coreSchema, TableSchemaDefinition $auditSchema): array
    {
        $expected = $this->generator->generate($coreSchema);
        $operations = [];

        $activeAuditMirrors = $this->activeMirrorColumns($auditSchema);
        $expectedMirrors = $this->mirrorColumnsByName($expected);

        foreach ($expectedMirrors as $columnName => $expectedColumn) {
            $currentColumn = $activeAuditMirrors[$columnName] ?? null;
            if ($currentColumn === null) {
                $operations[] = new PatchOperation(
                    PatchOperationType::AddColumn,
                    $auditSchema->tableName,
                    $columnName,
                    $expectedColumn->phinxType,
                    $expectedColumn->phinxOptions(),
                );
                continue;
            }

            if (!$this->columnsEquivalent($expectedColumn, $currentColumn)) {
                $operations[] = new PatchOperation(
                    PatchOperationType::ChangeColumn,
                    $auditSchema->tableName,
                    $columnName,
                    $expectedColumn->phinxType,
                    $expectedColumn->phinxOptions(),
                );
            }
        }

        foreach ($activeAuditMirrors as $columnName => $currentColumn) {
            if (!array_key_exists($columnName, $expectedMirrors)) {
                $operations[] = new PatchOperation(
                    PatchOperationType::RenameColumn,
                    $auditSchema->tableName,
                    $columnName,
                    newColumnName: $this->nextDroppedColumnName($auditSchema, $columnName),
                );
            }
        }

        return $operations;
    }

    /**
     * @return array<string, ColumnDefinition>
     */
    private function activeMirrorColumns(TableSchemaDefinition $auditSchema): array
    {
        $mirrors = [];

        foreach ($auditSchema->columns as $column) {
            if (in_array($column->name, AuditSchemaGenerator::metadataColumnNames(), true)) {
                continue;
            }

            if ($column->name === $auditSchema->primaryKey) {
                continue;
            }

            if (preg_match(self::DROPPED_COLUMN_PATTERN, $column->name)) {
                continue;
            }

            $mirrors[$column->name] = $column;
        }

        return $mirrors;
    }

    /**
     * @return array<string, ColumnDefinition>
     */
    private function mirrorColumnsByName(TableSchemaDefinition $auditSchema): array
    {
        $mirrors = [];

        foreach ($this->activeMirrorColumns($auditSchema) as $name => $column) {
            $mirrors[$name] = $column;
        }

        return $mirrors;
    }

    private function columnsEquivalent(ColumnDefinition $left, ColumnDefinition $right): bool
    {
        return $left->phinxType === $right->phinxType
            && $left->phinxOptions() == $right->phinxOptions();
    }

    private function nextDroppedColumnName(TableSchemaDefinition $auditSchema, string $columnName): string
    {
        $max = 0;

        foreach ($auditSchema->getColumnNames() as $existingName) {
            if (preg_match(self::DROPPED_COLUMN_PATTERN, $existingName, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'dropped_' . ($max + 1) . '_' . $columnName;
    }
}
