<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;

interface AuditMigrationGeneratorInterface
{
    /**
     * @return string[] generated file paths
     */
    public function generateCreateMigrations(
        string $outputDirectory,
        ?string $tableName = null,
        ?AuditConfiguration $configuration = null,
    ): array;

    /**
     * @return string[] generated file paths
     */
    public function generatePatchMigrations(
        string $outputDirectory,
        ?string $tableName = null,
        ?AuditConfiguration $configuration = null,
    ): array;
}
