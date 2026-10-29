<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property string $tax_period
 * @property string $return_type
 * @property string $status
 * @property string $taxpayer_name
 * @property string $taxpayer_bin
 * @property string $taxpayer_address
 * @property string $economic_activity
 * @property string|null $tax_circle
 * @property string|null $tax_commissionerate
 * @property string $sales_standard_rated_value_bdt
 * @property string $sales_standard_rated_vat_bdt
 * @property string $sales_retail_trade_value_bdt
 * @property string $sales_retail_trade_vat_bdt
 * @property string $sales_exempt_value_bdt
 * @property string $total_output_vat_bdt
 * @property string $local_purchase_value_bdt
 * @property string $local_purchase_vat_bdt
 * @property string $import_assessment_value_bdt
 * @property string $import_vat_rebatable_bdt
 * @property string $import_advance_tax_at_bdt
 * @property string $total_input_tax_credit_bdt
 * @property string $decreasing_adjustments_returns_bdt
 * @property string $decreasing_adjustments_other_bdt
 * @property string $total_decreasing_adjustments_bdt
 * @property string $increasing_adjustments_vds_bdt
 * @property string $increasing_adjustments_returns_bdt
 * @property string $total_increasing_adjustments_bdt
 * @property string $net_vat_payable_bdt
 * @property string $closing_balance_credit_bdt
 * @property string|null $submission_reference
 * @property CarbonInterface|null $submitted_at
 * @property string|null $treasury_challan_number
 * @property CarbonInterface|null $treasury_challan_date
 * @property string $treasury_deposit_amount_bdt
 * @property array<string, mixed>|null $summary_metadata
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property User|null $createdByUser
 */
class Mushak91Return extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'mushak_9_1_returns';

    protected $fillable = [
        'tax_period',
        'return_type',
        'status',
        'taxpayer_name',
        'taxpayer_bin',
        'taxpayer_address',
        'economic_activity',
        'tax_circle',
        'tax_commissionerate',
        'sales_standard_rated_value_bdt',
        'sales_standard_rated_vat_bdt',
        'sales_retail_trade_value_bdt',
        'sales_retail_trade_vat_bdt',
        'sales_exempt_value_bdt',
        'total_output_vat_bdt',
        'local_purchase_value_bdt',
        'local_purchase_vat_bdt',
        'import_assessment_value_bdt',
        'import_vat_rebatable_bdt',
        'import_advance_tax_at_bdt',
        'total_input_tax_credit_bdt',
        'decreasing_adjustments_returns_bdt',
        'decreasing_adjustments_other_bdt',
        'total_decreasing_adjustments_bdt',
        'increasing_adjustments_vds_bdt',
        'increasing_adjustments_returns_bdt',
        'total_increasing_adjustments_bdt',
        'net_vat_payable_bdt',
        'closing_balance_credit_bdt',
        'submission_reference',
        'submitted_at',
        'treasury_challan_number',
        'treasury_challan_date',
        'treasury_deposit_amount_bdt',
        'summary_metadata',
        'created_by_user_id',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'treasury_challan_date' => 'date',
            'summary_metadata' => 'array',
            'sales_standard_rated_value_bdt' => 'string',
            'sales_standard_rated_vat_bdt' => 'string',
            'sales_retail_trade_value_bdt' => 'string',
            'sales_retail_trade_vat_bdt' => 'string',
            'sales_exempt_value_bdt' => 'string',
            'total_output_vat_bdt' => 'string',
            'local_purchase_value_bdt' => 'string',
            'local_purchase_vat_bdt' => 'string',
            'import_assessment_value_bdt' => 'string',
            'import_vat_rebatable_bdt' => 'string',
            'import_advance_tax_at_bdt' => 'string',
            'total_input_tax_credit_bdt' => 'string',
            'decreasing_adjustments_returns_bdt' => 'string',
            'decreasing_adjustments_other_bdt' => 'string',
            'total_decreasing_adjustments_bdt' => 'string',
            'increasing_adjustments_vds_bdt' => 'string',
            'increasing_adjustments_returns_bdt' => 'string',
            'total_increasing_adjustments_bdt' => 'string',
            'net_vat_payable_bdt' => 'string',
            'closing_balance_credit_bdt' => 'string',
            'treasury_deposit_amount_bdt' => 'string',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted' && $this->submitted_at !== null;
    }

    public function isPayable(): bool
    {
        return (float) $this->net_vat_payable_bdt > 0.00;
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
