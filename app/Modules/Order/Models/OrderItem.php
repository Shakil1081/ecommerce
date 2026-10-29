<?php

declare(strict_types=1);

namespace App\Modules\Order\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $product_id
 * @property string $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property array<string, mixed>|null $attributes
 * @property int $quantity
 * @property string $unit_price_bdt
 * @property string $tier_savings_bdt
 * @property string $subtotal_bdt
 * @property string $unit_cogs_bdt
 * @property Order $order
 * @property Product $product
 * @property ProductVariant $variant
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'sku',
        'attributes',
        'quantity',
        'unit_price_bdt',
        'tier_savings_bdt',
        'subtotal_bdt',
        'unit_cogs_bdt',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'quantity' => 'integer',
            'unit_price_bdt' => 'decimal:4',
            'tier_savings_bdt' => 'decimal:4',
            'subtotal_bdt' => 'decimal:4',
            'unit_cogs_bdt' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
