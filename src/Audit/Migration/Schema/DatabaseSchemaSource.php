<?php

namespace Amtgard\AaroExtensions\Audit\Migration\Schema;

use Amtgard\AaroExtensions\Audit\Migration\MysqlTypeMapper;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;

final class DatabaseSchemaSource implements SchemaSourceInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function tableExists(string $tableName): bool
    {
        $escaped = str_replace("'", "''", $tableName);
        $this->database->clear();
        $result = $this->database->execute("SHOW TABLES LIKE '$escaped'");

        return $result->next();
    }

    /**
     * @return string[]
     */
    public function listCoreTables(): array
    {
        $this->database->clear();
        $result = $this->database->execute('SHOW TABLES');
        $tables = [];

        while ($result->next()) {
            $record = $result->getRecord();
            if (!is_array($record)) {
                continue;
            }

            $tableName = array_values($record)[0] ?? null;
            if (!is_string($tableName)) {
                continue;
            }

            if (str_ends_with($tableName, '_audit') || $tableName === 'phinxlog') {
                continue;
            }

            $tables[] = $tableName;
        }

        sort($tables);

        return $tables;
    }

    public function loadTableSchema(string $tableName): TableSchemaDefinition
    {
        $this->database->clear();
        $result = $this->database->execute("DESCRIBE `$tableName`");

        $columns = [];
        $primaryKey = null;

        while ($result->next()) {
            $field = FieldDefinition::fromDescribeTable($result);
            $columns[] = MysqlTypeMapper::fromFieldDefinition($field, $field->getNullable());

            if ($result->Key === 'PRI') {
                $primaryKey = $field->getName();
            }
        }

        if ($columns === []) {
            throw new \RuntimeException("Table '$tableName' has no columns or does not exist.");
        }

        return new TableSchemaDefinition($tableName, $columns, $primaryKey);
    }
}
