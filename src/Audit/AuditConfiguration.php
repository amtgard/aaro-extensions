<?php

namespace Amtgard\AaroExtensions\Audit;

use Amtgard\Traits\Builder\Builder;

final class AuditConfiguration
{
    use Builder;

    public const DEFAULT_AUDIT_TABLE_SUFFIX = '_audit';

    private ?string $auditTableName = null;

    /** @var callable(): (int|string|null)|EditedBySupplier|null */
    private mixed $editedBySupplier = null;

    private bool $auditInserts = true;

    private function __construct()
    {
    }

    public function auditInserts(): bool
    {
        return $this->auditInserts;
    }

    public function resolveAuditTableName(string $coreTableName): string
    {
        return $this->auditTableName ?? ($coreTableName . self::DEFAULT_AUDIT_TABLE_SUFFIX);
    }

    public function resolveEditedById(): int|string|null
    {
        if ($this->editedBySupplier === null) {
            return null;
        }

        $supplier = $this->editedBySupplier;
        if (is_callable($supplier)) {
            return $supplier();
        }

        if ($supplier instanceof EditedBySupplier) {
            return $supplier->getEditedById();
        }

        return null;
    }
}
