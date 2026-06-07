<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditIntegTestTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('aaro_audit_source')
            ->addColumn('int_value', 'integer', ['null' => true])
            ->addColumn('string_value', 'string', ['null' => true, 'limit' => 255])
            ->create();

        $this->table('aaro_audit_source_audit')
            ->addColumn('audit_id', 'integer', ['null' => false])
            ->addColumn('edit_at', 'datetime', ['null' => false])
            ->addColumn('edit_fields', 'json', ['null' => true])
            ->addColumn('edited_by_id', 'integer', ['null' => true])
            ->addColumn('operation', 'enum', ['values' => ['insert', 'update', 'delete'], 'null' => false])
            ->addColumn('int_value', 'integer', ['null' => true])
            ->addColumn('string_value', 'string', ['null' => true, 'limit' => 255])
            ->create();
    }
}
