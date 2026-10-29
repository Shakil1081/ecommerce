<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goods_received_note_id
 * @property int|null $import_po_item_id
 * @property int|null $local_po_item_id
 * @property string $product_variant_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property int $quantity_damaged
 * @property string|null $barcode_scanned
 * @property string|null $bin_location
 * @property string|null $discrepancy_reason
 * @property GoodsReceivedNote $goodsReceivedNote
 * @property ImportPoItem|null $importPoItem
 * @property LocalPoItem|null $localPoItem
 * @property ProductVariant $variant
 */
class GrnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_received_note_id',
        'import_po_item_id',
        'local_po_item_id',
        'product_variant_id',
        'quantity_ordered',
        'quantity_received',
        'quantity_damaged',
        'barcode_scanned',
        'bin_location',
        'discrepancy_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'quantity_damaged' => 'integer',
        ];
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'goods_received_note_id');
    }

    public function importPoItem(): BelongsTo
    {
        return $this->belongsTo(ImportPoItem::class, 'import_po_item_id');
    }

    public function localPoItem(): BelongsTo
    {
        return $this->belongsTo(LocalPoItem::class, 'local_po_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
