<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\Migration\AuditMigrationGeneratorInterface;
use Amtgard\AaroExtensions\Audit\Migration\AuditMigrationService;
use Amtgard\AaroExtensions\Audit\Migration\Cli\AuditMigrateCommand;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;

class AuditMigrateCommandTest extends AmtgardTestCase
{
    public function testParseArguments_parsesEqualsFormOptions(): void
    {
        $command = new AuditMigrateCommand();
        $args = $command->parseArguments([
            'aaro-audit-migrate',
            'phinx',
            '--env=./.env',
            '--out-dir=./db/migrations',
            '--table=users',
        ]);

        self::assertSame('phinx', $args['command']);
        self::assertSame('./.env', $args['env']);
        self::assertSame('./db/migrations', $args['out-dir']);
        self::assertSame('users', $args['table']);
    }

    public function testParseArguments_parsesSpaceSeparatedOptions(): void
    {
        $command = new AuditMigrateCommand();
        $args = $command->parseArguments([
            'aaro-audit-migrate',
            'patch',
            '--env',
            './test-resources',
            '--out-dir',
            '/tmp/migrations',
        ]);

        self::assertSame('patch', $args['command']);
        self::assertSame('./test-resources', $args['env']);
        self::assertSame('/tmp/migrations', $args['out-dir']);
    }

    public function testRun_returnsZeroForHelp(): void
    {
        $command = new AuditMigrateCommand();

        ob_start();
        $exitCode = $command->run(['aaro-audit-migrate', '--help']);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('aaro-audit-migrate', $output);
    }

    public function testRun_returnsOneWhenEnvMissing(): void
    {
        $command = new AuditMigrateCommand();

        $exitCode = $command->run(['aaro-audit-migrate', 'phinx', '--out-dir=/tmp/out']);

        self::assertSame(1, $exitCode);
    }

    public function testRun_returnsOneWhenOutDirMissing(): void
    {
        $command = new AuditMigrateCommand();

        $exitCode = $command->run(['aaro-audit-migrate', 'phinx', '--env=./.env']);

        self::assertSame(1, $exitCode);
    }

    public function testRun_patchWithInjectedService_printsNoChangesRequired(): void
    {
        $service = Phake::mock(AuditMigrationGeneratorInterface::class);
        Phake::when($service)->generatePatchMigrations('/tmp/out', 'users', Phake::ignoreRemaining())
            ->thenReturn([]);

        $command = new AuditMigrateCommand($service);

        ob_start();
        $exitCode = $command->run([
            'aaro-audit-migrate',
            'patch',
            '--env=./ignored.env',
            '--out-dir=/tmp/out',
            '--table=users',
        ]);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No changes required.', $output);
        Phake::verify($service)->generatePatchMigrations('/tmp/out', 'users', Phake::ignoreRemaining());
    }

    public function testRun_phinxWithInjectedService_printsGeneratedFiles(): void
    {
        $service = Phake::mock(AuditMigrationGeneratorInterface::class);
        Phake::when($service)->generateCreateMigrations('/tmp/out', 'users', Phake::ignoreRemaining())
            ->thenReturn(['/tmp/out/20260607120000_create_users_audit.php']);

        $command = new AuditMigrateCommand($service);

        ob_start();
        $exitCode = $command->run([
            'aaro-audit-migrate',
            'phinx',
            '--env=./ignored.env',
            '--out-dir=/tmp/out',
            '--table=users',
        ]);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Generated: /tmp/out/20260607120000_create_users_audit.php', $output);
    }

    public function testRun_returnsOneWhenServiceThrows(): void
    {
        $service = Phake::mock(AuditMigrationGeneratorInterface::class);
        Phake::when($service)->generateCreateMigrations(Phake::anyParameters())
            ->thenThrow(new \RuntimeException('Audit table already exists.'));

        $command = new AuditMigrateCommand($service);

        $exitCode = $command->run([
            'aaro-audit-migrate',
            'phinx',
            '--env=./ignored.env',
            '--out-dir=/tmp/out',
            '--table=users',
        ]);

        self::assertSame(1, $exitCode);
    }
}
