<?php

namespace Amtgard\AaroExtensions\Audit;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;

class AuditTableFactory extends TableFactory
{
    public static function mapperSupplier(
        Database $database,
        DataAccessPolicy $policy,
        string $mapperName,
        ?AuditConfiguration $auditConfiguration = null,
    ): EntityMapper {
        return EntityMapper::builder()
            ->table(self::build($database, $policy, $mapperName, $auditConfiguration))
            ->name($mapperName)
            ->build();
    }

    public static function build(
        Database $database,
        DataAccessPolicy $policy,
        string $tableName,
        ?AuditConfiguration $auditConfiguration = null,
    ): Table {
        $configuration = $auditConfiguration ?? AuditConfiguration::builder()->build();
        $auditTableName = $configuration->resolveAuditTableName($tableName);

        $srcTable = TableFactory::build($database, $policy, $tableName);
        $auditTable = TableFactory::build($database, $policy, $auditTableName);

        return AuditTable::builder()
            ->srcTable($srcTable)
            ->auditTable($auditTable)
            ->auditConfiguration($configuration)
            ->tableName($tableName)
            ->database($database)
            ->tableSchema($policy->applyTableSchemaPolicy($tableName))
            ->dataAccessPolicy($policy)
            ->queryBuilder(self::buildQueryBuilder($policy, $tableName))
            ->fieldSet(FieldSet::builder()->build())
            ->build();
    }
}
