<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use DomainException;

final class InsufficientStockException extends DomainException
{
    public static function forVariant(string $sku, int $requested, int $available): self
    {
        return new self("Insufficient inventory stock for SKU [{$sku}]. Requested: {$requested}, Available: {$available}.");
    }
}
