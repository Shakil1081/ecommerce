<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Resources;

use App\Modules\Cart\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
final class CartResource extends JsonResource
{
    public const FREE_SHIPPING_THRESHOLD = '2000.0000';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtotal = (string) $this->subtotal_bdt;
        $threshold = self::FREE_SHIPPING_THRESHOLD;

        $unlocked = bccomp($subtotal, $threshold, 4) >= 0;
        $remaining = $unlocked ? '0.0000' : bcsub($threshold, $subtotal, 4);

        $ratio = bcdiv($subtotal, $threshold, 4);
        $percent = min(100.0, max(0.0, (float) bcmul($ratio, '100', 2)));

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'guest_token' => $this->guest_token,
            'status' => $this->status,
            'coupon_code' => $this->coupon_code,
            'total_items_count' => $this->total_items_count,
            'subtotal_bdt' => $this->subtotal_bdt,
            'discount_amount_bdt' => $this->discount_amount_bdt,
            'total_bdt' => $this->total_bdt,
            'free_shipping' => [
                'threshold_bdt' => $threshold,
                'amount_remaining_bdt' => $remaining,
                'is_unlocked' => $unlocked,
                'progress_percent' => $percent,
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->variant?->product?->name ?? 'Product',
                    'sku' => $item->variant?->sku ?? '',
                    'attributes' => $item->variant?->getAttribute('attributes') ?? [],
                    'quantity' => $item->quantity,
                    'unit_price_bdt' => $item->unit_price_bdt,
                    'tier_savings_bdt' => $item->tier_savings_bdt,
                    'subtotal_bdt' => $item->subtotal_bdt,
                    'image_url' => $item->variant?->product?->getFirstMediaUrl('products', 'thumb_300') ?: null,
                ];
            }),
        ];
    }
}
