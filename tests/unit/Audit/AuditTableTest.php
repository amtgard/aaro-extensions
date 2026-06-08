<?php

namespace Tests\Unit\Audit;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\AuditTable;
use Amtgard\AaroExtensions\Audit\AuditTableFactory;
use Amtgard\AaroExtensions\Audit\AuditSnapshot;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

final class TestableAuditTable extends AuditTable
{
    /** @var array<string, mixed>|null */
    public ?array $lastAuditEntry = null;

    protected function writeAuditEntry(AuditSnapshot $snapshot): void
    {
        $this->lastAuditEntry = $this->buildAuditEntry($snapshot);
        parent::writeAuditEntry($snapshot);
    }
}

class AuditTableTest extends AmtgardTestCase
{
    public function testSave_onInsert_writesAuditEntryWithFullSnapshot(): void
    {
        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: false,
            setFields: ['field_a' => 'Alice', 'field_b' => 10],
            primaryKeyValue: 99,
            editedById: 55,
        );

        $auditTable->save();

        self::assertNotNull($auditTable->lastAuditEntry);
        self::assertSame(99, $auditTable->lastAuditEntry['audit_id']);
        self::assertSame('insert', $auditTable->lastAuditEntry['operation']);
        self::assertSame('[]', $auditTable->lastAuditEntry['edit_fields']);
        self::assertSame('Alice', $auditTable->lastAuditEntry['field_a']);
        self::assertSame(10, $auditTable->lastAuditEntry['field_b']);
        self::assertSame(55, $auditTable->lastAuditEntry['edited_by_id']);
        self::assertNotEmpty($auditTable->lastAuditEntry['edit_at']);
    }

    public function testSave_onInsert_canBeDisabled(): void
    {
        $configuration = AuditConfiguration::builder()
            ->auditInserts(false)
            ->build();

        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: false,
            setFields: ['field_a' => 'Alice'],
            configuration: $configuration,
        );

        $auditTable->save();

        self::assertNull($auditTable->lastAuditEntry);
    }

    public function testSave_onUpdateWithChanges_writesAuditEntry(): void
    {
        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: true,
            currentFieldValues: ['field_a' => 3],
            setFields: ['field_a' => 7],
            primaryKeyValue: 10,
            editedById: 55,
        );

        $auditTable->save();

        self::assertNotNull($auditTable->lastAuditEntry);
        self::assertSame(10, $auditTable->lastAuditEntry['audit_id']);
        self::assertSame('update', $auditTable->lastAuditEntry['operation']);
        self::assertSame('["field_a"]', $auditTable->lastAuditEntry['edit_fields']);
        self::assertSame(7, $auditTable->lastAuditEntry['field_a']);
        self::assertSame(55, $auditTable->lastAuditEntry['edited_by_id']);
        self::assertArrayNotHasKey('field_b', $auditTable->lastAuditEntry);
    }

    public function testSave_onUpdateWithoutChanges_doesNotWriteAuditEntry(): void
    {
        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: true,
            currentFieldValues: ['field_a' => 3],
            setFields: ['field_a' => 3],
        );

        $auditTable->save();

        self::assertNull($auditTable->lastAuditEntry);
    }

    public function testDelete_writesFullRecordToAuditEntry(): void
    {
        $deletedRecord = ['id' => 12, 'field_a' => 3, 'field_b' => 'gone'];
        $auditTable = $this->createAuditTableHarness(
            deletedRecord: $deletedRecord,
            primaryKeyValue: 12,
            editedById: 77,
        );

        $auditTable->delete();

        self::assertNotNull($auditTable->lastAuditEntry);
        self::assertSame(12, $auditTable->lastAuditEntry['audit_id']);
        self::assertSame('delete', $auditTable->lastAuditEntry['operation']);
        self::assertSame('[]', $auditTable->lastAuditEntry['edit_fields']);
        self::assertSame(3, $auditTable->lastAuditEntry['field_a']);
        self::assertSame('gone', $auditTable->lastAuditEntry['field_b']);
        self::assertSame(77, $auditTable->lastAuditEntry['edited_by_id']);
    }

    public function testDelete_withoutActiveRecordButPrimaryKeySet_writesFullRecordFromDatabase(): void
    {
        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: false,
            setFields: ['id' => 12],
            priorRowInDatabase: ['id' => 12, 'field_a' => 3, 'field_b' => 'gone'],
            primaryKeyValue: 12,
            editedById: 77,
        );

        $auditTable->delete();

        self::assertNotNull($auditTable->lastAuditEntry);
        self::assertSame(12, $auditTable->lastAuditEntry['audit_id']);
        self::assertSame('delete', $auditTable->lastAuditEntry['operation']);
        self::assertSame('[]', $auditTable->lastAuditEntry['edit_fields']);
        self::assertSame(3, $auditTable->lastAuditEntry['field_a']);
        self::assertSame('gone', $auditTable->lastAuditEntry['field_b']);
        self::assertSame(77, $auditTable->lastAuditEntry['edited_by_id']);
    }

    public function testSave_onUpdateWithoutActiveRecordButPrimaryKeySet_writesAuditEntry(): void
    {
        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: false,
            setFields: ['id' => 10, 'field_a' => 7],
            priorRowInDatabase: ['id' => 10, 'field_a' => 3],
            primaryKeyValue: 10,
            editedById: 55,
        );

        $auditTable->save();

        self::assertNotNull($auditTable->lastAuditEntry);
        self::assertSame(10, $auditTable->lastAuditEntry['audit_id']);
        self::assertSame('update', $auditTable->lastAuditEntry['operation']);
        self::assertSame('["field_a"]', $auditTable->lastAuditEntry['edit_fields']);
        self::assertSame(7, $auditTable->lastAuditEntry['field_a']);
    }

    public function testSave_onUpdateWithoutActiveRecordAndNoChanges_doesNotWriteAuditEntry(): void
    {
        $auditTable = $this->createAuditTableHarness(
            hasActiveRecord: false,
            setFields: ['id' => 10, 'field_a' => 3],
            priorRowInDatabase: ['id' => 10, 'field_a' => 3],
            primaryKeyValue: 10,
        );

        $auditTable->save();

        self::assertNull($auditTable->lastAuditEntry);
    }

    public function testDelegatesReadOperationsToSourceTable(): void
    {
        $auditTable = $this->createAuditTableHarness();
        $srcTable = $this->getSrcTable($auditTable);
        Phake::when($srcTable)->find()->thenReturn(3);

        self::assertSame(3, $auditTable->find());
        Phake::verify($srcTable)->find();
    }

    public function testDelegatesFieldAssignmentToSourceTable(): void
    {
        $auditTable = $this->createAuditTableHarness();
        $srcTable = $this->getSrcTable($auditTable);

        $auditTable->field_a = 99;

        Phake::verify($srcTable, Phake::times(1))->__set('field_a', 99);
    }

    private function getSrcTable(TestableAuditTable $auditTable): Table
    {
        $reflection = new \ReflectionProperty(AuditTable::class, 'srcTable');
        $reflection->setAccessible(true);

        return $reflection->getValue($auditTable);
    }

    /**
     * @param array<string, mixed>|null $priorRowInDatabase
     * @param array<string, mixed>|null $currentFieldValues
     * @param array<string, mixed>|null $setFields
     * @param array<string, mixed>|null $deletedRecord
     */
    private function createAuditTableHarness(
        bool $hasActiveRecord = false,
        ?array $priorRowInDatabase = null,
        ?array $currentFieldValues = null,
        ?array $setFields = null,
        ?array $deletedRecord = null,
        int $primaryKeyValue = 1,
        int $editedById = 1,
        ?AuditConfiguration $configuration = null,
    ): TestableAuditTable {
        $schema = Phake::mock(TableSchema::class);
        $primaryKeyField = \Amtgard\ActiveRecordOrm\Schema\FieldDefinition::builder()->name('id')->build();
        Phake::when($schema)->getPrimaryKey()->thenReturn($primaryKeyField);
        Phake::when($schema)->primaryKeyIsSet(Phake::anyParameters())->thenReturnCallback(
            function (FieldSet $fieldSet) use ($setFields): bool {
                return array_key_exists('id', $setFields ?? []);
            },
        );
        Phake::when($schema)->getTableName()->thenReturn('core_table');

        $fieldSet = FieldSet::builder()->build();
        foreach ($setFields ?? [] as $fieldName => $value) {
            $fieldSet->setField(
                \Amtgard\ActiveRecordOrm\Query\FieldOperation::builder()
                    ->field(\Amtgard\ActiveRecordOrm\Schema\FieldDefinition::builder()->name($fieldName)->build())
                    ->value($value)
                    ->operation(\Amtgard\ActiveRecordOrm\Query\Operation::Set)
                    ->build()
            );
        }

        $srcTable = Phake::mock(Table::class);
        Phake::when($srcTable)->hasActiveRecord()->thenReturn($hasActiveRecord);
        Phake::when($srcTable)->getTableSchema()->thenReturn($schema);
        Phake::when($srcTable)->getSetFields()->thenReturn($fieldSet);
        Phake::when($srcTable)->getPrimaryKeyValue()->thenReturn($primaryKeyValue);
        Phake::when($srcTable)->__get(Phake::anyParameters())->thenReturnCallback(
            function (string $name) use ($currentFieldValues): mixed {
                return ($currentFieldValues ?? [])[$name] ?? null;
            }
        );

        if ($priorRowInDatabase !== null) {
            $fetchResultSet = Phake::mock(\Amtgard\ActiveRecordOrm\RecordSet::class);
            Phake::when($fetchResultSet)->next()->thenReturn(true);
            Phake::when($fetchResultSet)->getRecord()->thenReturn($priorRowInDatabase);

            $fetchDatabase = Phake::mock(Database::class);
            Phake::when($fetchDatabase)->execute(Phake::anyParameters())->thenReturn($fetchResultSet);
            Phake::when($fetchDatabase)->clear()->thenReturn(null);
            Phake::when($srcTable)->getDatabase()->thenReturn($fetchDatabase);
        }

        if ($deletedRecord !== null) {
            $resultSet = Phake::mock(\Amtgard\ActiveRecordOrm\ResultSet::class);
            Phake::when($resultSet)->getFieldMap()->thenReturn($deletedRecord);
            Phake::when($srcTable)->getResultSet()->thenReturn($resultSet);
        }

        $database = Phake::mock(Database::class);
        $policy = Phake::mock(DataAccessPolicy::class);
        Phake::when($policy)->applyTableSchemaPolicy(Phake::anyParameters())->thenReturn($schema);
        Phake::when($policy)->applyQueryPolicy(Phake::anyParameters())->thenReturn(null);
        Phake::when($schema)->hasField(Phake::anyParameters())->thenReturn(true);
        Phake::when($schema)->getField(Phake::anyParameters())->thenReturn($primaryKeyField);

        $auditLogTable = Phake::mock(Table::class);
        Phake::when($auditLogTable)->clear()->thenReturn(null);
        Phake::when($auditLogTable)->save()->thenReturn(null);

        $configuration ??= AuditConfiguration::builder()
            ->editedBySupplier(fn () => $editedById)
            ->build();

        return TestableAuditTable::builder()
            ->srcTable($srcTable)
            ->auditTable($auditLogTable)
            ->auditConfiguration($configuration)
            ->tableName('core_table')
            ->database($database)
            ->tableSchema($schema)
            ->dataAccessPolicy($policy)
            ->queryBuilder(AuditTableFactory::buildQueryBuilder($policy, 'core_table'))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }
}
