<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Marketing\Models\CartRecoveryLog;
use App\Modules\Order\Models\Order;

class MarkCartAsRecoveredAction
{
    /**
     * Mark an abandoned cart and all its recovery logs as recovered upon order placement.
     */
    public function __invoke(Cart $cart, Order $order): void
    {
        $cart->update(['status' => 'converted']);

        CartRecoveryLog::where('cart_id', $cart->id)
            ->whereNull('recovered_at')
            ->update([
                'status' => CartRecoveryLog::STATUS_RECOVERED,
                'recovered_order_id' => $order->id,
                'recovered_at' => now(),
            ]);
    }
}
