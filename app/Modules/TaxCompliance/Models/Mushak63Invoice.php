<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Models\User;
use App\Modules\Order\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property int $order_id
 * @property string $invoice_number
 * @property string $tax_period
 * @property CarbonInterface $issue_date
 * @property string $seller_name
 * @property string $seller_bin
 * @property string $seller_address
 * @property string|null $tax_circle
 * @property string|null $tax_commissionerate
 * @property string $customer_name
 * @property string|null $customer_bin
 * @property string $customer_phone
 * @property string $customer_address
 * @property string $destination_address
 * @property string|null $vehicle_type_and_number
 * @property string $subtotal_exclusive_bdt
 * @property string $supplementary_duty_total_bdt
 * @property string $vat_total_bdt
 * @property string $shipping_fee_bdt
 * @property string $discount_amount_bdt
 * @property string $grand_total_bdt
 * @property bool $is_exempt
 * @property string $status
 * @property string $integrity_hash
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Order $order
 * @property Collection<int, Mushak63Item> $items
 * @property User|null $createdByUser
 */
class Mushak63Invoice extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'mushak_6_3_invoices';

    protected $fillable = [
        'order_id',
        'invoice_number',
        'tax_period',
        'issue_date',
        'seller_name',
        'seller_bin',
        'seller_address',
        'tax_circle',
        'tax_commissionerate',
        'customer_name',
        'customer_bin',
        'customer_phone',
        'customer_address',
        'destination_address',
        'vehicle_type_and_number',
        'subtotal_exclusive_bdt',
        'supplementary_duty_total_bdt',
        'vat_total_bdt',
        'shipping_fee_bdt',
        'discount_amount_bdt',
        'grand_total_bdt',
        'is_exempt',
        'status',
        'integrity_hash',
        'notes',
        'created_by_user_id',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
            'is_exempt' => 'boolean',
            'subtotal_exclusive_bdt' => 'string',
            'supplementary_duty_total_bdt' => 'string',
            'vat_total_bdt' => 'string',
            'shipping_fee_bdt' => 'string',
            'discount_amount_bdt' => 'string',
            'grand_total_bdt' => 'string',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Mushak63Item::class, 'mushak_6_3_invoice_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
