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
 * @property int $import_purchase_order_id
 * @property string $product_id
 * @property string $product_variant_id
 * @property string|null $hs_code
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property string $unit_price_foreign
 * @property string $unit_price_bdt
 * @property string $total_fob_foreign
 * @property string $total_fob_bdt
 * @property ImportPurchaseOrder $importPurchaseOrder
 * @property Product $product
 * @property ProductVariant $variant
 */
class ImportPoItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_purchase_order_id',
        'product_id',
        'product_variant_id',
        'hs_code',
        'quantity_ordered',
        'quantity_received',
        'unit_price_foreign',
        'unit_price_bdt',
        'total_fob_foreign',
        'total_fob_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_price_foreign' => 'decimal:4',
            'unit_price_bdt' => 'decimal:4',
            'total_fob_foreign' => 'decimal:4',
            'total_fob_bdt' => 'decimal:4',
        ];
    }

    public function importPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ImportPurchaseOrder::class, 'import_purchase_order_id');
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
