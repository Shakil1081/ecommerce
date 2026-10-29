<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Models\User;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property string $certificate_number
 * @property string $tax_period
 * @property CarbonInterface $issue_date
 * @property string $withholding_entity_name
 * @property string $withholding_entity_bin
 * @property string $withholding_entity_address
 * @property string|null $tax_circle
 * @property string|null $tax_commissionerate
 * @property int|null $supplier_id
 * @property string $supplier_name
 * @property string $supplier_bin
 * @property string|null $supplier_address
 * @property int|null $local_purchase_order_id
 * @property string $supplier_invoice_number
 * @property CarbonInterface $supplier_invoice_date
 * @property string $base_taxable_value_bdt
 * @property string $vat_rate_percent
 * @property string $vat_amount_bdt
 * @property string $vds_rate_percent
 * @property string $vds_deducted_amount_bdt
 * @property string $net_paid_to_supplier_bdt
 * @property string|null $treasury_challan_number
 * @property CarbonInterface|null $treasury_challan_date
 * @property string|null $treasury_bank_name
 * @property string|null $treasury_branch_name
 * @property string|null $treasury_account_code
 * @property string $status
 * @property string $integrity_hash
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Supplier|null $supplier
 * @property LocalPurchaseOrder|null $localPurchaseOrder
 * @property User|null $createdByUser
 */
class Mushak66Certificate extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'mushak_6_6_certificates';

    protected $fillable = [
        'certificate_number',
        'tax_period',
        'issue_date',
        'withholding_entity_name',
        'withholding_entity_bin',
        'withholding_entity_address',
        'tax_circle',
        'tax_commissionerate',
        'supplier_id',
        'supplier_name',
        'supplier_bin',
        'supplier_address',
        'local_purchase_order_id',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'base_taxable_value_bdt',
        'vat_rate_percent',
        'vat_amount_bdt',
        'vds_rate_percent',
        'vds_deducted_amount_bdt',
        'net_paid_to_supplier_bdt',
        'treasury_challan_number',
        'treasury_challan_date',
        'treasury_bank_name',
        'treasury_branch_name',
        'treasury_account_code',
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
            'supplier_invoice_date' => 'date',
            'treasury_challan_date' => 'date',
            'base_taxable_value_bdt' => 'string',
            'vat_rate_percent' => 'string',
            'vat_amount_bdt' => 'string',
            'vds_rate_percent' => 'string',
            'vds_deducted_amount_bdt' => 'string',
            'net_paid_to_supplier_bdt' => 'string',
        ];
    }

    public function isDeposited(): bool
    {
        return $this->status === 'deposited' && ! empty($this->treasury_challan_number);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function localPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LocalPurchaseOrder::class, 'local_purchase_order_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
