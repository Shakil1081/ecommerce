<?php

declare(strict_types=1);

namespace App\Modules\Order\Events;

use App\Models\User;
use App\Modules\Order\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderStatusChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
        public readonly ?User $changedBy = null
    ) {}
}
