<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Cart\Models\Cart;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Marketing\Actions\RecordCouponUsageAction;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateOrderFromCartAction
{
    public function __construct(
        private readonly RecordCouponUsageAction $recordCouponUsage
    ) {}

    /**
     * Convert an active shopping cart into an Order record with order items and initial status history.
     *
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_email?: ?string,
     *     ip_address?: ?string,
     *     device_fingerprint?: ?string,
     *     user_agent?: ?string,
     *     division_id: int,
     *     district_id: int,
     *     thana_id?: ?int,
     *     delivery_address: string,
     *     customer_notes?: ?string,
     *     payment_method?: string
     * }  $customerData
     *
     * @throws ValidationException
     */
    public function __invoke(Cart $cart, array $customerData, ?User $user = null): Order
    {
        $cart->loadMissing('items.variant.product');

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Cannot place an order with an empty shopping cart.'],
            ]);
        }

        return DB::transaction(function () use ($cart, $customerData, $user): Order {
            // Resolve district and delivery zone
            $district = BangladeshDistrict::findOrFail($customerData['district_id']);
            $deliveryZone = $district->zone_type;

            // Compute shipping fee based on delivery zone and free shipping eligibility
            $isFreeShipping = bccomp((string) $cart->subtotal_bdt, CartResource::FREE_SHIPPING_THRESHOLD, 4) >= 0;

            if ($cart->coupon_code) {
                $coupon = Coupon::query()->where('code', $cart->coupon_code)->first();
                if ($coupon && $coupon->discount_type === Coupon::TYPE_FREE_SHIPPING) {
                    $isFreeShipping = true;
                }
            }

            $shippingFee = '0.0000';
            if (! $isFreeShipping) {
                $shippingFee = match ($deliveryZone) {
                    'dhaka_suburbs' => '100.0000',
                    'outside_dhaka' => '130.0000',
                    default => '70.0000', // inside_dhaka
                };
            }

            $subtotal = (string) $cart->subtotal_bdt;
            $discount = (string) $cart->discount_amount_bdt;
            $total = bcadd(bcsub($subtotal, $discount, 4), $shippingFee, 4);
            if (bccomp($total, '0.0000', 4) < 0) {
                $total = '0.0000';
            }

            $paymentMethod = $customerData['payment_method'] ?? 'cash_on_delivery';
            $now = Carbon::now();
            $orderNumber = 'ORD-'.$now->format('Ymd').'-'.strtoupper(Str::random(6));

            /** @var Order $order */
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user?->id ?? $cart->user_id,
                'guest_token' => $cart->guest_token,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'payment_method' => $paymentMethod,
                'customer_name' => $customerData['customer_name'],
                'customer_phone' => $customerData['customer_phone'],
                'customer_email' => $customerData['customer_email'] ?? null,
                'ip_address' => $customerData['ip_address'] ?? (request()->hasHeader('X-Forwarded-For') ? request()->header('X-Forwarded-For') : request()->ip()),
                'device_fingerprint' => $customerData['device_fingerprint'] ?? (request()->header('X-Device-Fingerprint') ?: null),
                'user_agent' => $customerData['user_agent'] ?? request()->userAgent(),
                'division_id' => $customerData['division_id'],
                'district_id' => $customerData['district_id'],
                'thana_id' => $customerData['thana_id'] ?? null,
                'delivery_zone' => $deliveryZone,
                'delivery_address' => $customerData['delivery_address'],
                'customer_notes' => $customerData['customer_notes'] ?? null,
                'subtotal_bdt' => $subtotal,
                'discount_amount_bdt' => $discount,
                'shipping_fee_bdt' => $shippingFee,
                'total_bdt' => $total,
                'advance_paid_bdt' => '0.0000',
                'payable_cod_amount_bdt' => $total,
                'coupon_code' => $cart->coupon_code,
            ]);

            // Create Order Items
            foreach ($cart->items as $cartItem) {
                $variant = $cartItem->variant;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $variant?->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'product_name' => $variant?->product?->name ?? 'Product',
                    'sku' => $variant?->sku ?? '',
                    'attributes' => $variant?->getAttribute('attributes'),
                    'quantity' => $cartItem->quantity,
                    'unit_price_bdt' => $cartItem->unit_price_bdt,
                    'tier_savings_bdt' => $cartItem->tier_savings_bdt,
                    'subtotal_bdt' => $cartItem->subtotal_bdt,
                    'unit_cogs_bdt' => $variant?->purchase_cost_bdt ?? '0.0000',
                ]);
            }

            // Record Coupon usage if applied
            if ($cart->coupon_code) {
                /** @var Coupon|null $coupon */
                $coupon = Coupon::query()->where('code', $cart->coupon_code)->first();
                if ($coupon) {
                    ($this->recordCouponUsage)(
                        coupon: $coupon,
                        user: $user,
                        guestToken: $cart->guest_token,
                        orderId: $order->id,
                        discountAmountBdt: $discount
                    );
                }
            }

            // Create initial status history entry
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING,
                'changed_by_user_id' => $user?->id,
                'reason' => 'Order created from checkout cart',
                'metadata' => [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
                'created_at' => $now,
            ]);

            // Transition cart status to converted
            $cart->update([
                'status' => 'converted',
                'last_activity_at' => $now,
            ]);

            return $order->fresh(['items', 'statusHistories', 'division', 'district', 'thana']);
        });
    }
}
