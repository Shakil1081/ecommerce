<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mushak_6_8_debit_note_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $item_name
 * @property string $sku
 * @property int $quantity_returned
 * @property string $unit_price_exclusive_bdt
 * @property string $subtotal_exclusive_bdt
 * @property string $vat_rate_percent
 * @property string $vat_amount_bdt
 * @property string $total_debit_bdt
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Mushak68DebitNote $debitNote
 * @property Product|null $product
 * @property ProductVariant|null $variant
 */
class Mushak68Item extends Model
{
    use HasFactory;

    protected $table = 'mushak_6_8_items';

    protected $fillable = [
        'mushak_6_8_debit_note_id',
        'product_id',
        'product_variant_id',
        'item_name',
        'sku',
        'quantity_returned',
        'unit_price_exclusive_bdt',
        'subtotal_exclusive_bdt',
        'vat_rate_percent',
        'vat_amount_bdt',
        'total_debit_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'integer',
            'unit_price_exclusive_bdt' => 'string',
            'subtotal_exclusive_bdt' => 'string',
            'vat_rate_percent' => 'string',
            'vat_amount_bdt' => 'string',
            'total_debit_bdt' => 'string',
        ];
    }

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(Mushak68DebitNote::class, 'mushak_6_8_debit_note_id');
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
