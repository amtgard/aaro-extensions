<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditColumns;
use Amtgard\AaroExtensions\Audit\Migration\AuditSchemaGenerator;
use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;
use Amtgard\PHPUnit\AmtgardTestCase;

class AuditSchemaGeneratorTest extends AmtgardTestCase
{
    public function testGenerate_buildsMetadataAndNullableMirrors(): void
    {
        $coreSchema = new TableSchemaDefinition('users', [
            new ColumnDefinition('id', 'integer', ['null' => false, 'identity' => true]),
            new ColumnDefinition('name', 'string', ['null' => false, 'limit' => 255]),
            new ColumnDefinition('bio', 'text', ['null' => true]),
        ], 'id');

        $auditSchema = (new AuditSchemaGenerator())->generate($coreSchema);

        self::assertSame('users_audit', $auditSchema->tableName);
        self::assertNotNull($auditSchema->getColumn(AuditColumns::AUDIT_ID));
        self::assertNotNull($auditSchema->getColumn(AuditColumns::OPERATION));
        self::assertNull($auditSchema->getColumn('id'));

        $name = $auditSchema->getColumn('name');
        self::assertNotNull($name);
        self::assertTrue($name->isNullable());
        self::assertSame(255, $name->phinxOptions()['limit']);
    }
}
