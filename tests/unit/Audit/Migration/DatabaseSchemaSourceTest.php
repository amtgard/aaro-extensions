<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\Migration\AuditTableExclusions;
use Amtgard\AaroExtensions\Audit\Migration\Schema\DatabaseSchemaSource;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class DatabaseSchemaSourceTest extends AmtgardTestCase
{
    public function testListCoreTables_excludesConfiguredTables(): void
    {
        $database = Phake::mock(Database::class);
        $result = Phake::mock(RecordSet::class);

        Phake::when($database)->execute('SHOW TABLES')->thenReturn($result);
        Phake::when($result)->next()
            ->thenReturn(true)
            ->thenReturn(true)
            ->thenReturn(true)
            ->thenReturn(true)
            ->thenReturn(false);
        Phake::when($result)->getRecord()
            ->thenReturn(['Tables_in_test' => 'users'])
            ->thenReturn(['Tables_in_test' => 'users_audit'])
            ->thenReturn(['Tables_in_test' => 'phinxlog'])
            ->thenReturn(['Tables_in_test' => 'queue_jobs']);

        $source = new DatabaseSchemaSource(
            $database,
            new AuditTableExclusions(['phinxlog', 'queue_jobs'], ['_audit']),
        );

        self::assertSame(['users'], $source->listCoreTables());
    }
}
