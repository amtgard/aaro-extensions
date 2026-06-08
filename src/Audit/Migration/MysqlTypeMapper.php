<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

use Amtgard\AaroExtensions\Audit\Migration\Schema\ColumnDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;

final class MysqlTypeMapper
{
    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function fromNativeType(string $nativeType, bool $nullable): array
    {
        $normalized = strtolower($nativeType);

        if (str_starts_with($normalized, 'enum(')) {
            preg_match('/enum\((.+)\)/i', $nativeType, $matches);
            $values = self::parseEnumValues($matches[1] ?? '');

            return ['enum', ['null' => $nullable, 'values' => $values]];
        }

        if (preg_match('/^varchar\((\d+)\)/', $normalized, $matches)) {
            return ['string', ['null' => $nullable, 'limit' => (int) $matches[1]]];
        }

        if (preg_match('/^char\((\d+)\)/', $normalized, $matches)) {
            return ['char', ['null' => $nullable, 'limit' => (int) $matches[1]]];
        }

        return match (true) {
            str_starts_with($normalized, 'int'),
            str_starts_with($normalized, 'tinyint'),
            str_starts_with($normalized, 'smallint'),
            str_starts_with($normalized, 'mediumint'),
            str_starts_with($normalized, 'bigint') => ['integer', ['null' => $nullable]],
            str_starts_with($normalized, 'double'),
            str_starts_with($normalized, 'float') => ['float', ['null' => $nullable]],
            str_starts_with($normalized, 'decimal') => ['decimal', ['null' => $nullable, 'precision' => 10, 'scale' => 2]],
            str_starts_with($normalized, 'datetime'),
            str_starts_with($normalized, 'timestamp') => ['datetime', ['null' => $nullable]],
            str_starts_with($normalized, 'date') => ['date', ['null' => $nullable]],
            str_starts_with($normalized, 'json') => ['json', ['null' => $nullable]],
            str_starts_with($normalized, 'text'),
            str_starts_with($normalized, 'mediumtext'),
            str_starts_with($normalized, 'longtext') => ['text', ['null' => $nullable]],
            str_starts_with($normalized, 'blob'),
            str_starts_with($normalized, 'mediumblob'),
            str_starts_with($normalized, 'longblob') => ['blob', ['null' => $nullable]],
            default => ['string', ['null' => $nullable, 'limit' => 255]],
        };
    }

    public static function fromFieldDefinition(FieldDefinition $field, bool $nullable): ColumnDefinition
    {
        [$phinxType, $options] = self::fromNativeType($field->getNativeType(), $nullable);
        $options['null'] = $nullable;

        return new ColumnDefinition($field->getName(), $phinxType, $options);
    }

    /**
     * @return string[]
     */
    private static function parseEnumValues(string $rawValues): array
    {
        preg_match_all("/'((?:\\\\'|[^'])*)'/", $rawValues, $matches);

        return array_map(
            static fn (string $value) => str_replace("\\'", "'", $value),
            $matches[1] ?? [],
        );
    }
}
