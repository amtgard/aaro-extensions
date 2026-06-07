<?php

namespace Amtgard\AaroExtensions\Audit;

final class AuditSnapshot
{
    /**
     * @param string[] $editFields
     * @param array<string, mixed> $fieldValues
     */
    public function __construct(
        public readonly AuditOperation $operation,
        public readonly array $editFields,
        public readonly array $fieldValues,
        public readonly mixed $auditId,
    ) {
    }
}
