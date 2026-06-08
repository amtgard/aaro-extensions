<?php

namespace Tests\Integration\Audit;

use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\AaroExtensions\Audit\Migration\Cli\AuditMigrateCommand;
use Amtgard\PHPUnit\AmtgardTestCase;
use Dotenv\Dotenv;

class AuditMigrateCliIntegTest extends AmtgardTestCase
{
    private const CORE_TABLE = 'aaro_cli_migration_test';

    private static Database $db;

    private static string $dotenvPath;

    private string $outputDirectory;

    public static function setUpBeforeClass(): void
    {
        self::$dotenvPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'test-resources';
        $dotenvFile = self::$dotenvPath . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($dotenvFile)) {
            self::markTestSkipped('Dotenv file not found in ' . self::$dotenvPath);
        }

        $dotenv = Dotenv::createImmutable(self::$dotenvPath);
        $dotenv->safeLoad();

        $config = DatabaseConfiguration::fromEnvironment();
        $provider = MysqlPdoProvider::fromConfiguration($config);
        self::$db = Database::fromProvider($provider);
    }

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/aaro-audit-cli-integ-' . uniqid('', true);
        mkdir($this->outputDirectory);
        $this->resetCoreTable();
    }

    protected function tearDown(): void
    {
        $this->dropTable(self::CORE_TABLE);
        $this->dropTable(self::CORE_TABLE . '_audit');
        $this->removeDirectory($this->outputDirectory);
    }

    public function testPhinxCommand_generatesCreateMigrationForCoreTable(): void
    {
        $exitCode = $this->runCli([
            'phinx',
            '--env=' . self::$dotenvPath . '/.env',
            '--out-dir=' . $this->outputDirectory,
            '--table=' . self::CORE_TABLE,
        ]);

        self::assertSame(0, $exitCode);
        $files = glob($this->outputDirectory . '/*_create_' . self::CORE_TABLE . '_audit.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertStringContainsString(self::CORE_TABLE . '_audit', $contents);
        self::assertStringContainsString('audit_id', $contents);
        self::assertStringContainsString('operation', $contents);
        self::assertStringContainsString('name', $contents);
    }

    public function testPatchCommand_reportsNoChangesWhenAuditTableMatchesCore(): void
    {
        $this->createAuditTableInSyncWithCore();

        ob_start();
        $exitCode = $this->runCli([
            'patch',
            '--env=' . self::$dotenvPath . '/.env',
            '--out-dir=' . $this->outputDirectory,
            '--table=' . self::CORE_TABLE,
        ]);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No changes required.', $output);
        self::assertSame([], glob($this->outputDirectory . '/*.php') ?: []);
    }

    public function testPatchCommand_generatesMigrationWhenCoreColumnAdded(): void
    {
        $this->createAuditTableInSyncWithCore();

        self::$db->clear();
        self::$db->execute(
            'ALTER TABLE `' . self::CORE_TABLE . '` ADD COLUMN `email` VARCHAR(255) NULL',
        );

        $exitCode = $this->runCli([
            'patch',
            '--env=' . self::$dotenvPath . '/.env',
            '--out-dir=' . $this->outputDirectory,
            '--table=' . self::CORE_TABLE,
        ]);

        self::assertSame(0, $exitCode);
        $files = glob($this->outputDirectory . '/*_patch_audit_tables.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertStringContainsString("->addColumn('email', 'string'", $contents);
    }

    public function testBinScript_matchesCommandClass(): void
    {
        $this->resetCoreTable();
        $binPath = dirname(__DIR__, 3) . '/bin/aaro-audit-migrate';

        $command = sprintf(
            'php %s phinx --env=%s --out-dir=%s --table=%s',
            escapeshellarg($binPath),
            escapeshellarg(self::$dotenvPath . '/.env'),
            escapeshellarg($this->outputDirectory),
            escapeshellarg(self::CORE_TABLE),
        );

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode);
        self::assertNotEmpty($output);
        self::assertStringContainsString('Generated:', implode("\n", $output));
    }

    /**
     * @param string[] $args
     */
    private function runCli(array $args): int
    {
        return (new AuditMigrateCommand())->run(array_merge(['aaro-audit-migrate'], $args));
    }

    private function resetCoreTable(): void
    {
        $this->dropTable(self::CORE_TABLE . '_audit');
        $this->dropTable(self::CORE_TABLE);

        self::$db->clear();
        self::$db->execute(
            'CREATE TABLE `' . self::CORE_TABLE . '` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NULL
            )',
        );
    }

    private function createAuditTableInSyncWithCore(): void
    {
        $this->dropTable(self::CORE_TABLE . '_audit');

        self::$db->clear();
        self::$db->execute(
            'CREATE TABLE `' . self::CORE_TABLE . '_audit` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `audit_id` INT NOT NULL,
                `edit_at` DATETIME NOT NULL,
                `edit_fields` JSON NULL,
                `edited_by_id` INT NULL,
                `operation` ENUM(\'insert\', \'update\', \'delete\') NOT NULL,
                `name` VARCHAR(255) NULL
            )',
        );
    }

    private function dropTable(string $tableName): void
    {
        self::$db->clear();
        self::$db->execute('DROP TABLE IF EXISTS `' . $tableName . '`');
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
