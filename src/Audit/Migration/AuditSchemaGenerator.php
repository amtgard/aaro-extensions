<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditColumns;
use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\AuditOperation;
use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;

final class AuditSchemaGenerator
{
    /**
     * @return string[]
     */
    public static function metadataColumnNames(): array
    {
        return [
            AuditColumns::AUDIT_ID,
            AuditColumns::EDIT_AT,
            AuditColumns::EDIT_FIELDS,
            AuditColumns::EDITED_BY_ID,
            AuditColumns::OPERATION,
        ];
    }

    public function generate(
        TableSchemaDefinition $coreSchema,
        ?AuditConfiguration $configuration = null,
    ): TableSchemaDefinition {
        $configuration ??= AuditConfiguration::builder()->build();
        $auditTableName = $configuration->resolveAuditTableName($coreSchema->tableName);

        $columns = array_merge(
            $this->metadataColumns(),
            $this->mirroredColumns($coreSchema),
        );

        return new TableSchemaDefinition($auditTableName, $columns, 'id');
    }

    /**
     * @return ColumnDefinition[]
     */
    private function metadataColumns(): array
    {
        return [
            new ColumnDefinition(AuditColumns::AUDIT_ID, 'integer', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_AT, 'datetime', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_FIELDS, 'json', ['null' => true]),
            new ColumnDefinition(AuditColumns::EDITED_BY_ID, 'integer', ['null' => true]),
            new ColumnDefinition(
                AuditColumns::OPERATION,
                'enum',
                [
                    'null' => false,
                    'values' => array_map(
                        static fn (AuditOperation $operation) => $operation->value,
                        AuditOperation::cases(),
                    ),
                ],
            ),
        ];
    }

    /**
     * @return ColumnDefinition[]
     */
    private function mirroredColumns(TableSchemaDefinition $coreSchema): array
    {
        $mirrored = [];

        foreach ($coreSchema->columns as $column) {
            if ($column->name === $coreSchema->primaryKey) {
                continue;
            }

            $mirrored[] = new ColumnDefinition(
                $column->name,
                $column->phinxType,
                $this->nullableMirrorOptions($column),
            );
        }

        return $mirrored;
    }

    /**
     * @return array<string, mixed>
     */
    private function nullableMirrorOptions(ColumnDefinition $column): array
    {
        $options = $column->phinxOptions();
        $options['null'] = true;
        unset($options['identity']);

        return $options;
    }
}
