<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class GetActiveCartAction
{
    /**
     * Resolve or initialize the active shopping cart for an authenticated user or guest shopper.
     */
    public function __invoke(?User $user = null, ?string $guestToken = null): Cart
    {
        if ($user) {
            /** @var Cart $cart */
            $cart = Cart::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => 'active',
                ],
                [
                    'last_activity_at' => Carbon::now(),
                ]
            );

            return $cart;
        }

        $token = $guestToken ?: (string) Str::random(40);

        /** @var Cart $cart */
        $cart = Cart::firstOrCreate(
            [
                'guest_token' => $token,
                'status' => 'active',
            ],
            [
                'last_activity_at' => Carbon::now(),
            ]
        );

        return $cart;
    }
}
