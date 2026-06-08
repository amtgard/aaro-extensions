<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

final class AuditTableExclusions
{
    /**
     * @param string[] $tables
     * @param string[] $suffixes
     */
    public function __construct(
        private readonly array $tables = [],
        private readonly array $suffixes = [],
    ) {
    }

    public function isExcluded(string $tableName): bool
    {
        if (in_array($tableName, $this->tables, true)) {
            return true;
        }

        foreach ($this->suffixes as $suffix) {
            if ($suffix !== '' && str_ends_with($tableName, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $tables
     */
    public function withAdditionalTables(array $tables): self
    {
        return new self(
            [...$this->tables, ...$tables],
            $this->suffixes,
        );
    }

    /**
     * @return string[]
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * @return string[]
     */
    public function suffixes(): array
    {
        return $this->suffixes;
    }
}
