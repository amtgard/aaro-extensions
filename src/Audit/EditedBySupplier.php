<?php

namespace Amtgard\AaroExtensions\Audit;

/**
 * Resolves the id of the user performing an audited edit.
 */
interface EditedBySupplier
{
    public function getEditedById(): int|string|null;
}
