<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Symfony\Component\Yaml\Yaml;

final class AuditTableExclusionsLoader
{
    public static function defaultPath(): string
    {
        return dirname(__DIR__, 3) . '/resources/audit-table-exclusions.yaml';
    }

    public static function load(?string ...$paths): AuditTableExclusions
    {
        $tables = [];
        $suffixes = [];

        foreach ($paths as $path) {
            if ($path === null || $path === '') {
                continue;
            }

            if (!is_file($path)) {
                throw new \RuntimeException("Exclusions file not found: $path");
            }

            $parsed = Yaml::parseFile($path);
            if (!is_array($parsed)) {
                throw new \RuntimeException("Exclusions file must contain a YAML mapping: $path");
            }

            $tables = [...$tables, ...self::stringList($parsed['tables'] ?? [], 'tables', $path)];
            $suffixes = [...$suffixes, ...self::stringList($parsed['suffixes'] ?? [], 'suffixes', $path)];
        }

        return new AuditTableExclusions(
            self::unique($tables),
            self::unique($suffixes),
        );
    }

    /**
     * @return string[]
     */
    private static function stringList(mixed $value, string $key, string $path): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new \RuntimeException("Exclusions '$key' must be a list in $path");
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \RuntimeException("Exclusions '$key' entries must be non-empty strings in $path");
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param string[] $values
     * @return string[]
     */
    private static function unique(array $values): array
    {
        return array_values(array_unique($values));
    }
}
