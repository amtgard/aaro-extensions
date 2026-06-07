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
        if (!$srcTable->hasActiveRecord()) {
            return null;
        }

        $primaryKey = $srcTable->getTableSchema()->getPrimaryKey()->getName();
        $editFields = [];
        $fieldValues = [];

        foreach ($srcTable->getSetFields()->getFieldsByOperation([Operation::Set]) as $fieldOperation) {
            $fieldName = $fieldOperation->getField()->getName();
            if ($fieldName === $primaryKey) {
                continue;
            }

            $newValue = $fieldOperation->getValue();
            $currentValue = $srcTable->$fieldName;

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

    public static function forDelete(Table $srcTable): AuditSnapshot
    {
        $primaryKey = $srcTable->getTableSchema()->getPrimaryKey()->getName();
        $record = $srcTable->getResultSet()->getFieldMap();

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
