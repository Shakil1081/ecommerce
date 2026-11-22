<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;

/**
 * Where to send the customer right after an order is placed (cart checkout and 1-click fast order).
 *
 * Medium/high-risk orders get an SMS OTP and go to the verification screen first;
 * everything else goes straight to the order confirmation page.
 */
final class ResolvePostOrderRedirectAction
{
    public function __construct(
        private readonly SendOrderVerificationOtpAction $sendOtp,
    ) {}

    public function __invoke(Order $order, ?string $ipAddress = null): string
    {
        if ($order->requiresOtpVerification()) {
            ($this->sendOtp)($order, $ipAddress);

            return route('storefront.order-verify', ['order' => $order->order_number]);
        }

        return route('storefront.order-confirmed', ['order' => $order->order_number]);
    }
}
