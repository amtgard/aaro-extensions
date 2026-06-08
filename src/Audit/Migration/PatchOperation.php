<?php

namespace Amtgard\AaroExtensions\Audit\Migration;

enum PatchOperationType: string
{
    case AddColumn = 'add_column';
    case ChangeColumn = 'change_column';
    case RenameColumn = 'rename_column';
}

final class PatchOperation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly PatchOperationType $type,
        public readonly string $tableName,
        public readonly string $columnName,
        public readonly ?string $phinxType = null,
        public readonly array $options = [],
        public readonly ?string $newColumnName = null,
    ) {
    }
}
