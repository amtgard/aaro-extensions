<?php

namespace Amtgard\AaroExtensions\Audit\Migration\Schema;

final class TableSchemaDefinition
{
    /**
     * @param ColumnDefinition[] $columns
     */
    public function __construct(
        public readonly string $tableName,
        public readonly array $columns,
        public readonly ?string $primaryKey = 'id',
    ) {
    }

    public function getColumn(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getColumnNames(): array
    {
        return array_map(fn (ColumnDefinition $column) => $column->name, $this->columns);
    }
}
