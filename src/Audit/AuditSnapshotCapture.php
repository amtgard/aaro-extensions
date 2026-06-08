<?php

namespace Amtgard\AaroExtensions\Audit;

use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Table;

final class AuditSnapshotCapture
{
    public static function forInsert(Table $srcTable): AuditSnapshot
    {
        $primaryKey = $srcTable->getTableSchema()->getPrimaryKey()->getName();

        return new AuditSnapshot(
            AuditOperation::Insert,
            [],
            self::nonPrimaryFieldValuesFromSetFields($srcTable, $primaryKey),
            $srcTable->getPrimaryKeyValue(),
        );
    }

    public static function forUpdate(Table $srcTable): ?AuditSnapshot
    {
        if (!self::isUpdate($srcTable)) {
            return null;
        }

        $primaryKey = $srcTable->getTableSchema()->getPrimaryKey()->getName();
        $currentValues = self::currentFieldValues($srcTable, $primaryKey);
        $editFields = [];
        $fieldValues = [];

        foreach ($srcTable->getSetFields()->getFieldsByOperation([Operation::Set]) as $fieldOperation) {
            $fieldName = $fieldOperation->getField()->getName();
            if ($fieldName === $primaryKey) {
                continue;
            }

            $newValue = $fieldOperation->getValue();
            $currentValue = $currentValues[$fieldName] ?? null;

            if (!self::valuesEqual($currentValue, $newValue)) {
                $editFields[] = $fieldName;
                $fieldValues[$fieldName] = $newValue;
            }
        }

        if ($editFields === []) {
            return null;
        }

        return new AuditSnapshot(
            AuditOperation::Update,
            $editFields,
            $fieldValues,
            $srcTable->getPrimaryKeyValue(),
        );
    }

    public static function isUpdate(Table $srcTable): bool
    {
        return $srcTable->hasActiveRecord()
            || $srcTable->getTableSchema()->primaryKeyIsSet($srcTable->getSetFields());
    }

    public static function forDelete(Table $srcTable): AuditSnapshot
    {
        $primaryKey = $srcTable->getTableSchema()->getPrimaryKey()->getName();
        $record = self::deleteRecordValues($srcTable, $primaryKey);

        return new AuditSnapshot(
            AuditOperation::Delete,
            [],
            self::stripPrimaryKey($record, $primaryKey),
            $record[$primaryKey] ?? $srcTable->getPrimaryKeyValue(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function deleteRecordValues(Table $srcTable, string $primaryKey): array
    {
        if ($srcTable->hasActiveRecord()) {
            return $srcTable->getResultSet()->getFieldMap();
        }

        if ($srcTable->getTableSchema()->primaryKeyIsSet($srcTable->getSetFields())) {
            return self::fetchRowByPrimaryKey($srcTable, $primaryKey, $srcTable->getPrimaryKeyValue()) ?? [];
        }

        return $srcTable->getResultSet()->getFieldMap();
    }

    /**
     * @return array<string, mixed>
     */
    private static function currentFieldValues(Table $srcTable, string $primaryKey): array
    {
        if ($srcTable->hasActiveRecord()) {
            $values = [];

            foreach ($srcTable->getSetFields()->getFieldsByOperation([Operation::Set]) as $fieldOperation) {
                $fieldName = $fieldOperation->getField()->getName();
                if ($fieldName === $primaryKey) {
                    continue;
                }

                $values[$fieldName] = $srcTable->$fieldName;
            }

            return $values;
        }

        return self::fetchRowByPrimaryKey($srcTable, $primaryKey, $srcTable->getPrimaryKeyValue()) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchRowByPrimaryKey(
        Table $srcTable,
        string $primaryKey,
        mixed $primaryKeyValue,
    ): ?array {
        $database = $srcTable->getDatabase();
        $database->clear();
        $database->audit_pk = $primaryKeyValue;

        $result = $database->execute(
            sprintf(
                'SELECT * FROM `%s` WHERE `%s` = :audit_pk LIMIT 1',
                $srcTable->getTableSchema()->getTableName(),
                $primaryKey,
            ),
        );

        if (!$result->next()) {
            return null;
        }

        $record = $result->getRecord();

        return is_array($record) ? $record : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nonPrimaryFieldValuesFromSetFields(Table $srcTable, string $primaryKey): array
    {
        $fieldValues = [];

        foreach ($srcTable->getSetFields()->getFieldsByOperation([Operation::Set]) as $fieldOperation) {
            $fieldName = $fieldOperation->getField()->getName();
            if ($fieldName === $primaryKey) {
                continue;
            }

            $fieldValues[$fieldName] = $fieldOperation->getValue();
        }

        return $fieldValues;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private static function stripPrimaryKey(array $record, string $primaryKey): array
    {
        unset($record[$primaryKey]);

        return $record;
    }

    private static function valuesEqual(mixed $left, mixed $right): bool
    {
        return $left == $right;
    }
}
