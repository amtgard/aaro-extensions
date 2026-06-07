<?php

namespace Amtgard\AaroExtensions\Audit;

use Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;

class AuditTable extends Table implements TableInterface
{
    use Builder;

    protected Table $srcTable;
    protected Table $auditTable;
    protected AuditConfiguration $auditConfiguration;

    public function __set(string $name, $value): void
    {
        $this->srcTable->$name = $value;
    }

    public function __get(string $name)
    {
        return $this->srcTable->$name;
    }

    public function clear(): void
    {
        $this->srcTable->clear();
        $this->auditTable->clear();
    }

    public function orderBy(string $fieldName, OrderBy $orderBy): void
    {
        $this->srcTable->orderBy($fieldName, $orderBy);
    }

    public function select(mixed $fieldNameOrSet): void
    {
        $this->srcTable->select($fieldNameOrSet);
    }

    public function find(): int
    {
        return $this->srcTable->find();
    }

    public function count(string $countAlias = 'row_count'): int
    {
        return $this->srcTable->count($countAlias);
    }

    public function page(int $size = 10, int $page = 0): ActiveRecordTableInterface
    {
        return $this->srcTable->page($size, $page);
    }

    public function limit(int $offset, ?int $rowCount = null): void
    {
        $this->srcTable->limit($offset, $rowCount);
    }

    public function size(): int
    {
        return $this->srcTable->size();
    }

    public function next(): bool
    {
        return $this->srcTable->next();
    }

    public function hasActiveRecord(): bool
    {
        return $this->srcTable->hasActiveRecord();
    }

    public function getResultSet(): ResultSet
    {
        return $this->srcTable->getResultSet();
    }

    public function save(): void
    {
        if ($this->srcTable->hasActiveRecord()) {
            $snapshot = AuditSnapshotCapture::forUpdate($this->srcTable);
            $this->srcTable->save();
            if ($snapshot !== null) {
                $this->writeAuditEntry($snapshot);
            }
            return;
        }

        $this->srcTable->save();
        if ($this->auditConfiguration->auditInserts()) {
            $this->writeAuditEntry(AuditSnapshotCapture::forInsert($this->srcTable));
        }
    }

    public function delete(): void
    {
        $snapshot = AuditSnapshotCapture::forDelete($this->srcTable);
        $this->srcTable->delete();
        $this->writeAuditEntry($snapshot);
    }

    public function __call(string $name, array $arguments): void
    {
        call_user_func_array([$this->srcTable, $name], $arguments);
    }

    public function operation(string $name, Operation $operation, $value): void
    {
        $this->srcTable->operation($name, $operation, $value);
    }

    public function getPrimaryKeyValue()
    {
        return $this->srcTable->getPrimaryKeyValue();
    }

    public function getSetFields(): FieldSet
    {
        return $this->srcTable->getSetFields();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAuditEntry(AuditSnapshot $snapshot): array
    {
        return array_merge(
            [
                AuditColumns::AUDIT_ID => $snapshot->auditId,
                AuditColumns::EDIT_AT => date('Y-m-d H:i:s'),
                AuditColumns::EDIT_FIELDS => json_encode($snapshot->editFields, JSON_THROW_ON_ERROR),
                AuditColumns::EDITED_BY_ID => $this->auditConfiguration->resolveEditedById(),
                AuditColumns::OPERATION => $snapshot->operation->value,
            ],
            $snapshot->fieldValues,
        );
    }

    protected function writeAuditEntry(AuditSnapshot $snapshot): void
    {
        $this->auditTable->clear();
        foreach ($this->buildAuditEntry($snapshot) as $field => $value) {
            $this->auditTable->$field = $value;
        }
        $this->auditTable->save();
    }
}
