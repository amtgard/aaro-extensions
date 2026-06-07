<?php

namespace Tests\Unit\Audit;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\EditedBySupplier;
use Amtgard\PHPUnit\AmtgardTestCase;

class AuditConfigurationTest extends AmtgardTestCase
{
    public function testResolveAuditTableName_usesConventionByDefault(): void
    {
        $configuration = AuditConfiguration::builder()->build();

        self::assertSame('core_table_audit', $configuration->resolveAuditTableName('core_table'));
    }

    public function testResolveAuditTableName_usesInjectedName(): void
    {
        $configuration = AuditConfiguration::builder()
            ->auditTableName('custom_audit_table')
            ->build();

        self::assertSame('custom_audit_table', $configuration->resolveAuditTableName('core_table'));
    }

    public function testResolveEditedById_returnsNullWhenNoSupplier(): void
    {
        $configuration = AuditConfiguration::builder()->build();

        self::assertNull($configuration->resolveEditedById());
    }

    public function testResolveEditedById_usesCallableSupplier(): void
    {
        $configuration = AuditConfiguration::builder()
            ->editedBySupplier(fn () => 42)
            ->build();

        self::assertSame(42, $configuration->resolveEditedById());
    }

    public function testResolveEditedById_usesEditedBySupplierInterface(): void
    {
        $supplier = new class implements EditedBySupplier {
            public function getEditedById(): int
            {
                return 99;
            }
        };

        $configuration = AuditConfiguration::builder()
            ->editedBySupplier($supplier)
            ->build();

        self::assertSame(99, $configuration->resolveEditedById());
    }

    public function testAuditInserts_defaultsToTrue(): void
    {
        $configuration = AuditConfiguration::builder()->build();

        self::assertTrue($configuration->auditInserts());
    }

    public function testAuditInserts_canBeDisabled(): void
    {
        $configuration = AuditConfiguration::builder()
            ->auditInserts(false)
            ->build();

        self::assertFalse($configuration->auditInserts());
    }
}
