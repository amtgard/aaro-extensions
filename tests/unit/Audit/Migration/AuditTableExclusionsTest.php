<?php

namespace Tests\Unit\Audit\Migration;

use Amtgard\AaroExtensions\Audit\Migration\AuditTableExclusions;
use Amtgard\AaroExtensions\Audit\Migration\AuditTableExclusionsLoader;
use Amtgard\PHPUnit\AmtgardTestCase;

class AuditTableExclusionsTest extends AmtgardTestCase
{
    public function testIsExcluded_matchesExactTableNames(): void
    {
        $exclusions = new AuditTableExclusions(['phinxlog'], []);

        self::assertTrue($exclusions->isExcluded('phinxlog'));
        self::assertFalse($exclusions->isExcluded('users'));
    }

    public function testIsExcluded_matchesSuffixes(): void
    {
        $exclusions = new AuditTableExclusions([], ['_audit']);

        self::assertTrue($exclusions->isExcluded('users_audit'));
        self::assertFalse($exclusions->isExcluded('users'));
    }

    public function testLoad_readsBundledDefaults(): void
    {
        $exclusions = AuditTableExclusionsLoader::load(AuditTableExclusionsLoader::defaultPath());

        self::assertContains('phinxlog', $exclusions->tables());
        self::assertContains('_audit', $exclusions->suffixes());
        self::assertTrue($exclusions->isExcluded('phinxlog'));
        self::assertTrue($exclusions->isExcluded('users_audit'));
        self::assertFalse($exclusions->isExcluded('users'));
    }

    public function testLoad_mergesAdditionalExclusionFile(): void
    {
        $customFile = sys_get_temp_dir() . '/aaro-exclusions-' . uniqid('', true) . '.yaml';
        file_put_contents($customFile, "tables:\n  - queue_jobs\n");

        try {
            $exclusions = AuditTableExclusionsLoader::load(
                AuditTableExclusionsLoader::defaultPath(),
                $customFile,
            );

            self::assertTrue($exclusions->isExcluded('phinxlog'));
            self::assertTrue($exclusions->isExcluded('queue_jobs'));
        } finally {
            unlink($customFile);
        }
    }

    public function testLoad_throwsWhenFileMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Exclusions file not found');

        AuditTableExclusionsLoader::load('/tmp/does-not-exist-aaro-exclusions.yaml');
    }
}
