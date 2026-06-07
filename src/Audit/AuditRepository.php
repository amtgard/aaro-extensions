<?php

namespace Amtgard\AaroExtensions\Audit;

use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;

/**
 * Repository base for tables that write audit rows on update and delete.
 *
 * Configure EntityManager with AuditTableFactory::mapperSupplier() (or use AuditRepositoryEntityTrait)
 * so the underlying mapper uses AuditTable.
 */
abstract class AuditRepository extends Repository
{
    abstract public static function auditConfiguration(): AuditConfiguration;
}
