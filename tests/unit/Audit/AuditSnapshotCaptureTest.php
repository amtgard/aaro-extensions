<?php

namespace Tests\Unit\Audit;

use Amtgard\AaroExtensions\Audit\AuditOperation;
use Amtgard\AaroExtensions\Audit\AuditSnapshotCapture;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class AuditSnapshotCaptureTest extends AmtgardTestCase
{
    public function testForInsert_capturesNewValuesWithoutPrimaryKey(): void
    {
        $table = $this->createTableMock(
            hasActiveRecord: false,
            setFields: ['id' => 99, 'field_a' => 'Alice', 'field_b' => 10],
            primaryKeyValue: 99,
        );

        $snapshot = AuditSnapshotCapture::forInsert($table);

        self::assertSame(AuditOperation::Insert, $snapshot->operation);
        self::assertSame([], $snapshot->editFields);
        self::assertSame(99, $snapshot->auditId);
        self::assertSame(['field_a' => 'Alice', 'field_b' => 10], $snapshot->fieldValues);
    }

    public function testForUpdate_returnsNullWhenNotAnUpdate(): void
    {
        $table = $this->createTableMock(hasActiveRecord: false, setFields: ['field_a' => 'Alice']);

        self::assertNull(AuditSnapshotCapture::forUpdate($table));
    }

    public function testForUpdate_withoutActiveRecord_fetchesRowAndCapturesChanges(): void
    {
        $schema = $this->createSchema('id');
        Phake::when($schema)->primaryKeyIsSet(Phake::anyParameters())->thenReturn(true);
        Phake::when($schema)->getTableName()->thenReturn('users');

        $fieldSet = FieldSet::builder()->build();
        foreach (['id' => 5, 'field_a' => 7] as $fieldName => $value) {
            $fieldSet->setField(
                FieldOperation::builder()
                    ->field(FieldDefinition::builder()->name($fieldName)->build())
                    ->value($value)
                    ->operation(Operation::Set)
                    ->build()
            );
        }

        $recordSet = Phake::mock(\Amtgard\ActiveRecordOrm\RecordSet::class);
        Phake::when($recordSet)->next()->thenReturn(true);
        Phake::when($recordSet)->getRecord()->thenReturn(['id' => 5, 'field_a' => 3, 'field_b' => 'old']);

        $database = Phake::mock(\Amtgard\ActiveRecordOrm\Repository\Database::class);
        Phake::when($database)->execute(Phake::anyParameters())->thenReturn($recordSet);
        Phake::when($database)->clear()->thenReturn(null);

        $table = Phake::mock(Table::class);
        Phake::when($table)->hasActiveRecord()->thenReturn(false);
        Phake::when($table)->getTableSchema()->thenReturn($schema);
        Phake::when($table)->getSetFields()->thenReturn($fieldSet);
        Phake::when($table)->getPrimaryKeyValue()->thenReturn(5);
        Phake::when($table)->getDatabase()->thenReturn($database);

        $snapshot = AuditSnapshotCapture::forUpdate($table);

        self::assertNotNull($snapshot);
        self::assertSame(AuditOperation::Update, $snapshot->operation);
        self::assertSame(['field_a'], $snapshot->editFields);
        self::assertSame(['field_a' => 7], $snapshot->fieldValues);
    }

    public function testIsUpdate_isTrueWhenPrimaryKeyIsSetWithoutActiveRecord(): void
    {
        $schema = $this->createSchema('id');
        Phake::when($schema)->primaryKeyIsSet(Phake::anyParameters())->thenReturn(true);

        $table = Phake::mock(Table::class);
        Phake::when($table)->hasActiveRecord()->thenReturn(false);
        Phake::when($table)->getTableSchema()->thenReturn($schema);
        Phake::when($table)->getSetFields()->thenReturn(FieldSet::builder()->build());

        self::assertTrue(AuditSnapshotCapture::isUpdate($table));
    }

    public function testForUpdate_capturesNewValuesForChangedFieldsOnly(): void
    {
        $table = $this->createTableMock(
            hasActiveRecord: true,
            currentFieldValues: ['field_a' => 3, 'field_b' => 'old'],
            setFields: ['field_a' => 7, 'field_b' => 'old'],
            primaryKeyValue: 5,
        );

        $snapshot = AuditSnapshotCapture::forUpdate($table);

        self::assertNotNull($snapshot);
        self::assertSame(AuditOperation::Update, $snapshot->operation);
        self::assertSame(['field_a'], $snapshot->editFields);
        self::assertSame(5, $snapshot->auditId);
        self::assertSame(['field_a' => 7], $snapshot->fieldValues);
    }

    public function testForUpdate_excludesPrimaryKeyFromEditFields(): void
    {
        $table = $this->createTableMock(
            hasActiveRecord: true,
            currentFieldValues: ['id' => 1, 'field_a' => 3],
            setFields: ['id' => 2, 'field_a' => 7],
            primaryKeyValue: 2,
        );

        $snapshot = AuditSnapshotCapture::forUpdate($table);

        self::assertNotNull($snapshot);
        self::assertSame(['field_a'], $snapshot->editFields);
        self::assertSame(['field_a' => 7], $snapshot->fieldValues);
    }

    public function testForUpdate_returnsNullWhenNoFieldsChanged(): void
    {
        $table = $this->createTableMock(
            hasActiveRecord: true,
            currentFieldValues: ['field_a' => 3],
            setFields: ['field_a' => 3],
        );

        self::assertNull(AuditSnapshotCapture::forUpdate($table));
    }

    public function testForDelete_capturesFinalValuesWithoutPrimaryKey(): void
    {
        $record = ['id' => 5, 'field_a' => 3, 'field_b' => 'value'];
        $resultSet = Phake::mock(ResultSet::class);
        Phake::when($resultSet)->getFieldMap()->thenReturn($record);

        $table = Phake::mock(Table::class);
        Phake::when($table)->getResultSet()->thenReturn($resultSet);
        Phake::when($table)->getTableSchema()->thenReturn($this->createSchema('id'));
        Phake::when($table)->getPrimaryKeyValue()->thenReturn(5);

        $snapshot = AuditSnapshotCapture::forDelete($table);

        self::assertSame(AuditOperation::Delete, $snapshot->operation);
        self::assertSame([], $snapshot->editFields);
        self::assertSame(5, $snapshot->auditId);
        self::assertSame(['field_a' => 3, 'field_b' => 'value'], $snapshot->fieldValues);
    }

    /**
     * @param array<string, mixed> $currentFieldValues
     * @param array<string, mixed> $setFields
     */
    private function createTableMock(
        bool $hasActiveRecord,
        array $setFields = [],
        array $currentFieldValues = [],
        mixed $primaryKeyValue = 1,
        string $primaryKey = 'id',
    ): Table {
        $schema = $this->createSchema($primaryKey);
        $fieldSet = FieldSet::builder()->build();

        foreach ($setFields as $fieldName => $value) {
            $fieldSet->setField(
                FieldOperation::builder()
                    ->field(FieldDefinition::builder()->name($fieldName)->build())
                    ->value($value)
                    ->operation(Operation::Set)
                    ->build()
            );
        }

        $table = Phake::mock(Table::class);
        Phake::when($table)->hasActiveRecord()->thenReturn($hasActiveRecord);
        Phake::when($table)->getTableSchema()->thenReturn($schema);
        Phake::when($table)->getSetFields()->thenReturn($fieldSet);
        Phake::when($table)->getPrimaryKeyValue()->thenReturn($primaryKeyValue);
        Phake::when($table)->__get(Phake::anyParameters())->thenReturnCallback(
            function (string $name) use ($currentFieldValues): mixed {
                return $currentFieldValues[$name] ?? null;
            }
        );

        return $table;
    }

    private function createSchema(string $primaryKey): TableSchema
    {
        $schema = Phake::mock(TableSchema::class);
        Phake::when($schema)->getPrimaryKey()->thenReturn(
            FieldDefinition::builder()->name($primaryKey)->build()
        );
        Phake::when($schema)->primaryKeyIsSet(Phake::anyParameters())->thenReturn(false);
        Phake::when($schema)->getTableName()->thenReturn('test_table');

        return $schema;
    }
}
