<?php

namespace Amtgard\AaroExtensions\Audit\Migration\Schema;

interface SchemaSourceInterface
{
    public function tableExists(string $tableName): bool;

    /**
     * @return string[]
     */
    public function listCoreTables(): array;

    public function loadTableSchema(string $tableName): TableSchemaDefinition;
}
