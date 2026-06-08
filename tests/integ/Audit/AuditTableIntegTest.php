<?php

namespace Tests\Integration\Audit;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\AuditTableFactory;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\PHPUnit\AmtgardTestCase;
use Dotenv\Dotenv;

class AuditTableIntegTest extends AmtgardTestCase
{
    private const EDITED_BY_ID = 1001;

    private static Database $db;

    private static DataAccessPolicy $tablePolicy;

    private static TableInterface $auditTable;

    public static function setUpBeforeClass(): void
    {
        $dotenvPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'test-resources';
        $dotenvFile = $dotenvPath . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($dotenvFile)) {
            self::markTestSkipped('Dotenv file not found in ' . $dotenvPath);
        }

        $dotenv = Dotenv::createImmutable($dotenvPath);
        $dotenv->safeLoad();

        $config = DatabaseConfiguration::fromEnvironment();
        $provider = MysqlPdoProvider::fromConfiguration($config);
        self::$db = Database::fromProvider($provider);
        self::$tablePolicy = UncachedDataAccessPolicy::builder()->database(self::$db)->build();

        $auditConfiguration = AuditConfiguration::builder()
            ->editedBySupplier(fn () => self::EDITED_BY_ID)
            ->build();

        self::$auditTable = AuditTableFactory::build(
            self::$db,
            self::$tablePolicy,
            'aaro_audit_source',
            $auditConfiguration,
        );

        self::resetTables();
    }

    protected function setUp(): void
    {
        self::resetTables();
    }

    public function testInsert_writesAuditSnapshot(): void
    {
        self::$auditTable->clear();
        self::$auditTable->int_value = 2;
        self::$auditTable->string_value = 'hello';
        self::$auditTable->save();

        $row = $this->fetchLatestAuditRow();

        self::assertSame('insert', $row['operation']);
        self::assertNotEmpty($row['audit_id']);
        self::assertSame([], $this->decodeEditFields($row['edit_fields']));
        self::assertSame(2, (int) $row['int_value']);
        self::assertSame('hello', $row['string_value']);
        self::assertSame(self::EDITED_BY_ID, (int) $row['edited_by_id']);
        self::assertNotEmpty($row['edit_at']);
    }

    public function testUpdate_writesChangedFieldsOnly(): void
    {
        $this->insertSourceRow(intValue: 2, stringValue: 'hello');
        $coreId = $this->fetchCoreIdByIntValue(2);

        self::$auditTable->clear();
        self::$auditTable->int_value = 2;
        self::assertSame(1, self::$auditTable->find());
        self::assertTrue(self::$auditTable->next());
        self::$auditTable->int_value = 4;
        self::$auditTable->save();

        self::assertSame(2, $this->countAuditRows());
        $row = $this->fetchLatestAuditRow();

        self::assertSame('update', $row['operation']);
        self::assertSame($coreId, (int) $row['audit_id']);
        self::assertSame(['int_value'], $this->decodeEditFields($row['edit_fields']));
        self::assertSame(4, (int) $row['int_value']);
        self::assertNull($row['string_value']);
    }

    public function testUpdate_withNoChanges_doesNotWriteAuditRow(): void
    {
        $this->insertSourceRow(intValue: 2, stringValue: 'hello');

        self::$auditTable->clear();
        self::$auditTable->int_value = 2;
        self::assertSame(1, self::$auditTable->find());
        self::assertTrue(self::$auditTable->next());
        self::$auditTable->int_value = 2;
        self::$auditTable->save();

        self::assertSame(1, $this->countAuditRows());
        $row = $this->fetchLatestAuditRow();
        self::assertSame('insert', $row['operation']);
    }

    public function testUpdate_withoutLoadingRow_writesUpdateNotInsert(): void
    {
        $this->insertSourceRow(intValue: 2, stringValue: 'hello');
        $coreId = $this->fetchCoreIdByIntValue(2);

        self::$auditTable->clear();
        self::$auditTable->id = $coreId;
        self::$auditTable->string_value = 'updated without find';
        self::assertFalse(self::$auditTable->hasActiveRecord());
        self::$auditTable->save();

        self::assertSame(2, $this->countAuditRows());
        $row = $this->fetchLatestAuditRow();

        self::assertSame('update', $row['operation']);
        self::assertSame($coreId, (int) $row['audit_id']);
        self::assertSame(['string_value'], $this->decodeEditFields($row['edit_fields']));
        self::assertSame('updated without find', $row['string_value']);
        self::assertNull($row['int_value']);
    }

    public function testDelete_withoutLoadingRow_writesFullSnapshot(): void
    {
        $this->insertSourceRow(intValue: 2, stringValue: 'hello');
        $coreId = $this->fetchCoreIdByIntValue(2);

        self::$auditTable->clear();
        self::$auditTable->id = $coreId;
        self::assertFalse(self::$auditTable->hasActiveRecord());
        self::$auditTable->delete();

        self::assertSame(2, $this->countAuditRows());
        $row = $this->fetchLatestAuditRow();

        self::assertSame('delete', $row['operation']);
        self::assertSame($coreId, (int) $row['audit_id']);
        self::assertSame([], $this->decodeEditFields($row['edit_fields']));
        self::assertSame(2, (int) $row['int_value']);
        self::assertSame('hello', $row['string_value']);
        self::assertSame(0, $this->countCoreRows());
    }

    public function testDelete_writesFullSnapshot(): void
    {
        $this->insertSourceRow(intValue: 2, stringValue: 'hello');
        $coreId = $this->fetchCoreIdByIntValue(2);

        self::$auditTable->clear();
        self::$auditTable->int_value = 2;
        self::assertSame(1, self::$auditTable->find());
        self::assertTrue(self::$auditTable->next());
        self::$auditTable->delete();

        self::assertSame(2, $this->countAuditRows());
        $row = $this->fetchLatestAuditRow();

        self::assertSame('delete', $row['operation']);
        self::assertSame($coreId, (int) $row['audit_id']);
        self::assertSame([], $this->decodeEditFields($row['edit_fields']));
        self::assertSame(2, (int) $row['int_value']);
        self::assertSame('hello', $row['string_value']);
        self::assertSame(0, $this->countCoreRows());
    }

    private function insertSourceRow(int $intValue, string $stringValue): void
    {
        self::$auditTable->clear();
        self::$auditTable->int_value = $intValue;
        self::$auditTable->string_value = $stringValue;
        self::$auditTable->save();
    }

    private function fetchCoreIdByIntValue(int $intValue): int
    {
        $table = TableFactory::build(self::$db, self::$tablePolicy, 'aaro_audit_source');
        $table->clear();
        $table->int_value = $intValue;
        self::assertSame(1, $table->find());
        self::assertTrue($table->next());

        return (int) $table->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchLatestAuditRow(): array
    {
        self::$db->clear();
        $rows = self::$db->execute(
            'SELECT * FROM aaro_audit_source_audit ORDER BY id DESC LIMIT 1'
        );

        self::assertTrue($rows->next());

        $record = $rows->getRecord();
        self::assertIsArray($record);

        return $record;
    }

    private function countAuditRows(): int
    {
        self::$db->clear();
        $rows = self::$db->execute('SELECT COUNT(*) AS row_count FROM aaro_audit_source_audit');
        self::assertTrue($rows->next());

        return (int) $rows->row_count;
    }

    private function countCoreRows(): int
    {
        self::$db->clear();
        $rows = self::$db->execute('SELECT COUNT(*) AS row_count FROM aaro_audit_source');
        self::assertTrue($rows->next());

        return (int) $rows->row_count;
    }

    /**
     * @return string[]
     */
    private function decodeEditFields(mixed $editFields): array
    {
        if (is_array($editFields)) {
            return $editFields;
        }

        self::assertIsString($editFields);

        return json_decode($editFields, true, flags: JSON_THROW_ON_ERROR);
    }

    private static function resetTables(): void
    {
        self::$db->clear();
        self::$db->execute('SET FOREIGN_KEY_CHECKS=0');
        self::$db->execute('TRUNCATE TABLE aaro_audit_source_audit');
        self::$db->execute('TRUNCATE TABLE aaro_audit_source');
        self::$db->execute('SET FOREIGN_KEY_CHECKS=1');
    }
}
