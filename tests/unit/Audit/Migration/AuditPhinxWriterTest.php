<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditColumns;
use Amtgard\AaroExtensions\Audit\Migration\AuditPhinxWriter;
use Amtgard\AaroExtensions\Audit\Migration\PatchOperation;
use Amtgard\AaroExtensions\Audit\Migration\PatchOperationType;
use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;
use Amtgard\PHPUnit\AmtgardTestCase;

class AuditPhinxWriterTest extends AmtgardTestCase
{
    public function testWriteCreateMigration_rendersAuditTable(): void
    {
        $schema = new TableSchemaDefinition('users_audit', [
            new ColumnDefinition(AuditColumns::AUDIT_ID, 'integer', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_AT, 'datetime', ['null' => false]),
            new ColumnDefinition('name', 'string', ['null' => true, 'limit' => 255]),
        ]);

        $migration = (new AuditPhinxWriter())->writeCreateMigration(
            $schema,
            '20260607120000_create_users_audit.php',
        );

        self::assertStringContainsString('final class CreateUsersAudit extends AbstractMigration', $migration);
        self::assertStringContainsString('$this->table("users_audit")', $migration);
        self::assertStringContainsString("->addColumn('audit_id', 'integer', ['null' => false])", $migration);
        self::assertStringContainsString("->addColumn('name', 'string', ['null' => true, 'limit' => 255])", $migration);
        self::assertStringContainsString('->create();', $migration);
    }

    public function testWritePatchMigration_rendersRenameColumn(): void
    {
        $migration = (new AuditPhinxWriter())->writePatchMigration(
            [
                new PatchOperation(
                    PatchOperationType::RenameColumn,
                    'users_audit',
                    'legacy_code',
                    newColumnName: 'dropped_1_legacy_code',
                ),
            ],
            '20260607130000_patch_users_audit.php',
        );

        self::assertStringContainsString("->renameColumn('legacy_code', 'dropped_1_legacy_code')", $migration);
    }
}
