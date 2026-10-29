<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property string $product_variant_id
 * @property int $quantity
 * @property string $unit_price_bdt
 * @property string $tier_savings_bdt
 * @property string $subtotal_bdt
 * @property Cart $cart
 * @property ProductVariant $variant
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_variant_id',
        'quantity',
        'unit_price_bdt',
        'tier_savings_bdt',
        'subtotal_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_bdt' => 'decimal:4',
            'tier_savings_bdt' => 'decimal:4',
            'subtotal_bdt' => 'decimal:4',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
