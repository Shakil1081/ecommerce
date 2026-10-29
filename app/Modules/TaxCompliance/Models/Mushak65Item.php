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
 * @property int $mushak_6_5_challan_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $item_name
 * @property string $sku
 * @property string|null $hs_code
 * @property string $unit_of_measure
 * @property int $quantity
 * @property string $unit_cost_bdt
 * @property string $total_valuation_bdt
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Mushak65Challan $challan
 * @property Product|null $product
 * @property ProductVariant|null $variant
 */
class Mushak65Item extends Model
{
    use HasFactory;

    protected $table = 'mushak_6_5_items';

    protected $fillable = [
        'mushak_6_5_challan_id',
        'product_id',
        'product_variant_id',
        'item_name',
        'sku',
        'hs_code',
        'unit_of_measure',
        'quantity',
        'unit_cost_bdt',
        'total_valuation_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost_bdt' => 'string',
            'total_valuation_bdt' => 'string',
        ];
    }

    public function challan(): BelongsTo
    {
        return $this->belongsTo(Mushak65Challan::class, 'mushak_6_5_challan_id');
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
