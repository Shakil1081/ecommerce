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
 * @property int $mushak_6_3_invoice_id
 * @property int|null $order_item_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $item_name
 * @property string $sku
 * @property string|null $hs_code
 * @property string $unit_of_measure
 * @property int $quantity
 * @property string $unit_price_exclusive_bdt
 * @property string $subtotal_exclusive_bdt
 * @property string $sd_rate_percent
 * @property string $sd_amount_bdt
 * @property string $vat_rate_percent
 * @property string $vat_amount_bdt
 * @property string $total_with_tax_bdt
 * @property bool $is_vat_exempt
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Mushak63Invoice $invoice
 * @property OrderItem|null $orderItem
 * @property Product|null $product
 * @property ProductVariant|null $variant
 */
class Mushak63Item extends Model
{
    use HasFactory;

    protected $table = 'mushak_6_3_items';

    protected $fillable = [
        'mushak_6_3_invoice_id',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'item_name',
        'sku',
        'hs_code',
        'unit_of_measure',
        'quantity',
        'unit_price_exclusive_bdt',
        'subtotal_exclusive_bdt',
        'sd_rate_percent',
        'sd_amount_bdt',
        'vat_rate_percent',
        'vat_amount_bdt',
        'total_with_tax_bdt',
        'is_vat_exempt',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_vat_exempt' => 'boolean',
            'unit_price_exclusive_bdt' => 'string',
            'subtotal_exclusive_bdt' => 'string',
            'sd_rate_percent' => 'string',
            'sd_amount_bdt' => 'string',
            'vat_rate_percent' => 'string',
            'vat_amount_bdt' => 'string',
            'total_with_tax_bdt' => 'string',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Mushak63Invoice::class, 'mushak_6_3_invoice_id');
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
