<?php

declare(strict_types=1);

namespace App\Modules\Order\Exceptions;

use DomainException;

final class IllegalOrderStateTransitionException extends DomainException
{
    public static function make(string $orderNumber, string $fromStatus, string $toStatus): self
    {
        return new self("Cannot transition order [{$orderNumber}] from [{$fromStatus}] to [{$toStatus}]. This transition is not allowed by the order lifecycle state machine.");
    }
}
