<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Order\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rma_id
 * @property int $order_item_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $item_name
 * @property string $sku
 * @property int $quantity_requested
 * @property int $quantity_approved
 * @property string $unit_price_bdt
 * @property string $line_refund_bdt
 * @property string|null $condition_note
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property ReturnMerchandiseAuthorization $rma
 * @property OrderItem $orderItem
 * @property Product|null $product
 * @property ProductVariant|null $variant
 */
class RmaItem extends Model
{
    use HasFactory;

    protected $table = 'rma_items';

    protected $fillable = [
        'rma_id',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'item_name',
        'sku',
        'quantity_requested',
        'quantity_approved',
        'unit_price_bdt',
        'line_refund_bdt',
        'condition_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'unit_price_bdt' => 'string',
            'line_refund_bdt' => 'string',
        ];
    }

    public function rma(): BelongsTo
    {
        return $this->belongsTo(ReturnMerchandiseAuthorization::class, 'rma_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
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
