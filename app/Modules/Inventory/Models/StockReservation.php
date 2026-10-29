<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Cart\Models\Cart;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Order\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $order_id
 * @property int|null $cart_id
 * @property string $product_variant_id
 * @property int $warehouse_id
 * @property int $quantity
 * @property string $status
 * @property CarbonInterface $expires_at
 * @property Order|null $order
 * @property Cart|null $cart
 * @property ProductVariant $variant
 * @property Warehouse $warehouse
 */
class StockReservation extends Model
{
    use HasFactory;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'ulid',
        'order_id',
        'cart_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockReservation $reservation): void {
            if (empty($reservation->ulid)) {
                $reservation->ulid = (string) Str::ulid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESERVED)
            ->where('expires_at', '>', Carbon::now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESERVED)
            ->where('expires_at', '<=', Carbon::now());
    }
}
