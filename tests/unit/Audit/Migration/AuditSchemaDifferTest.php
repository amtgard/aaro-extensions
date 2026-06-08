<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditColumns;
use Amtgard\AaroExtensions\Audit\Migration\AuditSchemaDiffer;
use Amtgard\AaroExtensions\Audit\Migration\PatchOperationType;
use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;
use Amtgard\PHPUnit\AmtgardTestCase;

class AuditSchemaDifferTest extends AmtgardTestCase
{
    public function testDiff_addsMissingMirrorColumn(): void
    {
        $coreSchema = new TableSchemaDefinition('users', [
            new ColumnDefinition('id', 'integer', ['null' => false]),
            new ColumnDefinition('name', 'string', ['null' => false, 'limit' => 255]),
            new ColumnDefinition('email', 'string', ['null' => true, 'limit' => 255]),
        ]);

        $auditSchema = new TableSchemaDefinition('users_audit', [
            new ColumnDefinition(AuditColumns::AUDIT_ID, 'integer', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_AT, 'datetime', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_FIELDS, 'json', ['null' => true]),
            new ColumnDefinition(AuditColumns::EDITED_BY_ID, 'integer', ['null' => true]),
            new ColumnDefinition(AuditColumns::OPERATION, 'enum', ['null' => false, 'values' => ['insert', 'update', 'delete']]),
            new ColumnDefinition('name', 'string', ['null' => true, 'limit' => 255]),
        ]);

        $operations = (new AuditSchemaDiffer())->diff($coreSchema, $auditSchema);

        self::assertCount(1, $operations);
        self::assertSame(PatchOperationType::AddColumn, $operations[0]->type);
        self::assertSame('email', $operations[0]->columnName);
    }

    public function testDiff_renamesRemovedCoreColumnToDroppedPrefix(): void
    {
        $coreSchema = new TableSchemaDefinition('users', [
            new ColumnDefinition('id', 'integer', ['null' => false]),
            new ColumnDefinition('name', 'string', ['null' => false, 'limit' => 255]),
        ]);

        $auditSchema = new TableSchemaDefinition('users_audit', [
            new ColumnDefinition(AuditColumns::AUDIT_ID, 'integer', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_AT, 'datetime', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_FIELDS, 'json', ['null' => true]),
            new ColumnDefinition(AuditColumns::EDITED_BY_ID, 'integer', ['null' => true]),
            new ColumnDefinition(AuditColumns::OPERATION, 'enum', ['null' => false, 'values' => ['insert', 'update', 'delete']]),
            new ColumnDefinition('name', 'string', ['null' => true, 'limit' => 255]),
            new ColumnDefinition('legacy_code', 'string', ['null' => true, 'limit' => 32]),
        ]);

        $operations = (new AuditSchemaDiffer())->diff($coreSchema, $auditSchema);

        self::assertCount(1, $operations);
        self::assertSame(PatchOperationType::RenameColumn, $operations[0]->type);
        self::assertSame('legacy_code', $operations[0]->columnName);
        self::assertSame('dropped_1_legacy_code', $operations[0]->newColumnName);
    }
}
