<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $local_purchase_order_id
 * @property string $product_id
 * @property string $product_variant_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property string $unit_price_bdt
 * @property string $subtotal_bdt
 * @property string $vat_rate_percent
 * @property string $vat_amount_bdt
 * @property string $total_item_cost_bdt
 * @property LocalPurchaseOrder $localPurchaseOrder
 * @property Product $product
 * @property ProductVariant $variant
 */
class LocalPoItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_purchase_order_id',
        'product_id',
        'product_variant_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price_bdt',
        'subtotal_bdt',
        'vat_rate_percent',
        'vat_amount_bdt',
        'total_item_cost_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_price_bdt' => 'decimal:4',
            'subtotal_bdt' => 'decimal:4',
            'vat_rate_percent' => 'decimal:2',
            'vat_amount_bdt' => 'decimal:4',
            'total_item_cost_bdt' => 'decimal:4',
        ];
    }

    public function localPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LocalPurchaseOrder::class, 'local_purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Calculate item subtotal, VAT amount, and total item cost.
     */
    public function calculateItemTotals(): void
    {
        $subtotal = bcmul((string) $this->unit_price_bdt, (string) $this->quantity_ordered, 4);
        $vat = bcdiv(bcmul($subtotal, (string) $this->vat_rate_percent, 6), '100', 4);
        $total = bcadd($subtotal, $vat, 4);

        $this->update([
            'subtotal_bdt' => $subtotal,
            'vat_amount_bdt' => $vat,
            'total_item_cost_bdt' => $total,
        ]);
    }
}
