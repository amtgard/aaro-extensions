<?php

namespace Amtgard\AaroExtensions\Audit;

use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Optional\Optional;

trait AuditRepositoryEntityTrait
{
    private static ?EntityManager $auditEntityManager = null;

    protected function getEntityManager(): EntityManager
    {
        return Optional::ofNullable(static::$auditEntityManager)
            ->orElseGet(function () {
                $configuration = static::auditConfiguration();

                static::$auditEntityManager = EntityManager::builder()
                    ->database(EntityManager::getManager()->getDatabase())
                    ->dataAccessPolicy(EntityManager::getManager()->getDataAccessPolicy())
                    ->repositoryPolicy(UncachedPolicy::builder()->build())
                    ->mapperSupplier(fn ($database, $policy, $tableName) => AuditTableFactory::mapperSupplier(
                        $database,
                        $policy,
                        $tableName,
                        $configuration,
                    ))
                    ->build();

                return static::$auditEntityManager;
            });
    }

    abstract public static function auditConfiguration(): AuditConfiguration;
}
