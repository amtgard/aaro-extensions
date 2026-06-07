<?php

namespace Tests\Unit\Audit;

use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\AuditTable;
use Amtgard\AaroExtensions\Audit\AuditTableFactory;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class AuditTableFactoryTest extends AmtgardTestCase
{
    private Database $mockDatabase;
    private DataAccessPolicy $mockPolicy;
    private TableSchema $mockSourceTableSchema;
    private TableSchema $mockAuditTableSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockPolicy = Phake::mock(DataAccessPolicy::class);
        $this->mockSourceTableSchema = Phake::mock(TableSchema::class);
        $this->mockAuditTableSchema = Phake::mock(TableSchema::class);

        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_table')->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_table_audit')->thenReturn($this->mockAuditTableSchema);
    }

    public function testBuild_createsAuditTableInstance(): void
    {
        $table = AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, 'test_table');

        self::assertInstanceOf(AuditTable::class, $table);
        self::assertInstanceOf(TableInterface::class, $table);
    }

    public function testBuild_usesAuditSuffixByConvention(): void
    {
        AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, 'users');

        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy('users');
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy('users_audit');
    }

    public function testBuild_usesInjectedAuditTableName(): void
    {
        $configuration = AuditConfiguration::builder()
            ->auditTableName('users_history')
            ->build();

        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('users_history')->thenReturn($this->mockAuditTableSchema);

        AuditTableFactory::build($this->mockDatabase, $this->mockPolicy, 'users', $configuration);

        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy('users_history');
        Phake::verify($this->mockPolicy, Phake::never())->applyTableSchemaPolicy('users_audit');
    }

    public function testMapperSupplier_createsEntityMapper(): void
    {
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_mapper')->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_mapper_audit')->thenReturn($this->mockAuditTableSchema);

        $mapper = AuditTableFactory::mapperSupplier($this->mockDatabase, $this->mockPolicy, 'test_mapper');

        self::assertInstanceOf(EntityMapper::class, $mapper);
        self::assertSame('test_mapper', $mapper->getName());
        self::assertInstanceOf(AuditTable::class, $mapper->getTable());
    }

    public function testMapperSupplier_passesAuditConfiguration(): void
    {
        $configuration = AuditConfiguration::builder()
            ->auditTableName('test_mapper_custom_audit')
            ->build();

        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_mapper')->thenReturn($this->mockSourceTableSchema);
        Phake::when($this->mockPolicy)->applyTableSchemaPolicy('test_mapper_custom_audit')->thenReturn($this->mockAuditTableSchema);

        $mapper = AuditTableFactory::mapperSupplier(
            $this->mockDatabase,
            $this->mockPolicy,
            'test_mapper',
            $configuration,
        );

        self::assertInstanceOf(EntityMapper::class, $mapper);
        Phake::verify($this->mockPolicy, Phake::atLeast(1))->applyTableSchemaPolicy('test_mapper_custom_audit');
    }
}
