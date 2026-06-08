<?php

namespace Amtgard\AaroExtensions\Audit\Migration\Schema;

final class ColumnDefinition
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $name,
        public readonly string $phinxType,
        public readonly array $options = [],
    ) {
    }

    public function isNullable(): bool
    {
        return (bool) ($this->options['null'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function phinxOptions(): array
    {
        return $this->options;
    }
}
