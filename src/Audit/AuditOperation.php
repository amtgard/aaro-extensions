<?php

namespace Amtgard\AaroExtensions\Audit;

enum AuditOperation: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
}
