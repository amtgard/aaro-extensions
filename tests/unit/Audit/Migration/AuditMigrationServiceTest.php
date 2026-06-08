<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\AuditColumns;
use Amtgard\AaroExtensions\Audit\Migration\AuditMigrationService;
use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\AaroExtensions\Audit\Migration\Schema\SchemaSourceInterface;
use Amtgard\AaroExtensions\Audit\Migration\Schema\TableSchemaDefinition;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class AuditMigrationServiceTest extends AmtgardTestCase
{
    private SchemaSourceInterface $schemaSource;

    private AuditMigrationService $service;

    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaSource = Phake::mock(SchemaSourceInterface::class);
        $this->service = new AuditMigrationService($this->schemaSource);
        $this->outputDirectory = sys_get_temp_dir() . '/aaro-audit-service-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDirectory);
        parent::tearDown();
    }

    public function testGenerateCreateMigrations_writesSingleTableMigration(): void
    {
        Phake::when($this->schemaSource)->tableExists('users_audit')->thenReturn(false);
        Phake::when($this->schemaSource)->loadTableSchema('users')->thenReturn($this->coreUsersSchema());

        $files = $this->service->generateCreateMigrations($this->outputDirectory, 'users');

        self::assertCount(1, $files);
        self::assertFileExists($files[0]);
        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertStringContainsString('users_audit', $contents);
        self::assertStringContainsString(AuditColumns::AUDIT_ID, $contents);
        self::assertStringContainsString('name', $contents);
    }

    public function testGenerateCreateMigrations_throwsWhenAuditTableAlreadyExists(): void
    {
        Phake::when($this->schemaSource)->tableExists('users_audit')->thenReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Audit table 'users_audit' already exists.");

        $this->service->generateCreateMigrations($this->outputDirectory, 'users');
    }

    public function testGeneratePatchMigrations_returnsEmptyWhenSchemasMatch(): void
    {
        $coreSchema = $this->coreUsersSchema();
        $auditSchema = $this->matchingUsersAuditSchema();

        Phake::when($this->schemaSource)->tableExists('users_audit')->thenReturn(true);
        Phake::when($this->schemaSource)->loadTableSchema('users')->thenReturn($coreSchema);
        Phake::when($this->schemaSource)->loadTableSchema('users_audit')->thenReturn($auditSchema);

        $files = $this->service->generatePatchMigrations($this->outputDirectory, 'users');

        self::assertSame([], $files);
    }

    public function testGeneratePatchMigrations_writesMigrationWhenColumnAddedToCore(): void
    {
        $coreSchema = new TableSchemaDefinition('users', [
            new ColumnDefinition('id', 'integer', ['null' => false]),
            new ColumnDefinition('name', 'string', ['null' => false, 'limit' => 255]),
            new ColumnDefinition('email', 'string', ['null' => true, 'limit' => 255]),
        ]);
        $auditSchema = $this->matchingUsersAuditSchema();

        Phake::when($this->schemaSource)->tableExists('users_audit')->thenReturn(true);
        Phake::when($this->schemaSource)->loadTableSchema('users')->thenReturn($coreSchema);
        Phake::when($this->schemaSource)->loadTableSchema('users_audit')->thenReturn($auditSchema);

        $files = $this->service->generatePatchMigrations($this->outputDirectory, 'users');

        self::assertCount(1, $files);
        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertStringContainsString("->addColumn('email', 'string'", $contents);
    }

    public function testGeneratePatchMigrations_throwsWhenAuditTableMissing(): void
    {
        Phake::when($this->schemaSource)->tableExists('users_audit')->thenReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Run `aaro-audit-migrate phinx` first.');

        $this->service->generatePatchMigrations($this->outputDirectory, 'users');
    }

    private function coreUsersSchema(): TableSchemaDefinition
    {
        return new TableSchemaDefinition('users', [
            new ColumnDefinition('id', 'integer', ['null' => false]),
            new ColumnDefinition('name', 'string', ['null' => false, 'limit' => 255]),
        ]);
    }

    private function matchingUsersAuditSchema(): TableSchemaDefinition
    {
        return new TableSchemaDefinition('users_audit', [
            new ColumnDefinition(AuditColumns::AUDIT_ID, 'integer', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_AT, 'datetime', ['null' => false]),
            new ColumnDefinition(AuditColumns::EDIT_FIELDS, 'json', ['null' => true]),
            new ColumnDefinition(AuditColumns::EDITED_BY_ID, 'integer', ['null' => true]),
            new ColumnDefinition(
                AuditColumns::OPERATION,
                'enum',
                ['null' => false, 'values' => ['insert', 'update', 'delete']],
            ),
            new ColumnDefinition('name', 'string', ['null' => true, 'limit' => 255]),
        ]);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($directory);
    }
}
