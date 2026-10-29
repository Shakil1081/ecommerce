<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $import_purchase_order_id
 * @property string $boe_number
 * @property CarbonInterface $boe_date
 * @property string $customs_station
 * @property string|null $c_and_f_agent_name
 * @property string|null $c_and_f_license_number
 * @property string $customs_assessment_value_bdt
 * @property string $customs_duty_cd
 * @property string $regulatory_duty_rd
 * @property string $supplementary_duty_sd
 * @property string $vat_import
 * @property string $advance_tax_at
 * @property string $advance_income_tax_ait
 * @property string $customs_fine_penalty
 * @property string $total_customs_duties_bdt
 * @property string $payment_status
 * @property CarbonInterface|null $customs_payment_date
 * @property string|null $customs_challan_number
 * @property string $assessment_status
 * @property CarbonInterface|null $out_of_charge_date
 * @property string|null $notes
 * @property ImportPurchaseOrder $importPurchaseOrder
 * @property Collection<int, BoeItem> $items
 */
class BillOfEntry extends Model
{
    use HasFactory;

    protected $table = 'bills_of_entry';

    protected $fillable = [
        'ulid',
        'import_purchase_order_id',
        'boe_number',
        'boe_date',
        'customs_station',
        'c_and_f_agent_name',
        'c_and_f_license_number',
        'customs_assessment_value_bdt',
        'customs_duty_cd',
        'regulatory_duty_rd',
        'supplementary_duty_sd',
        'vat_import',
        'advance_tax_at',
        'advance_income_tax_ait',
        'customs_fine_penalty',
        'total_customs_duties_bdt',
        'payment_status',
        'customs_payment_date',
        'customs_challan_number',
        'assessment_status',
        'out_of_charge_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'boe_date' => 'date',
            'customs_payment_date' => 'date',
            'out_of_charge_date' => 'date',
            'customs_assessment_value_bdt' => 'decimal:4',
            'customs_duty_cd' => 'decimal:4',
            'regulatory_duty_rd' => 'decimal:4',
            'supplementary_duty_sd' => 'decimal:4',
            'vat_import' => 'decimal:4',
            'advance_tax_at' => 'decimal:4',
            'advance_income_tax_ait' => 'decimal:4',
            'customs_fine_penalty' => 'decimal:4',
            'total_customs_duties_bdt' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BillOfEntry $boe): void {
            if (empty($boe->ulid)) {
                $boe->ulid = (string) Str::ulid();
            }
        });
    }

    public function importPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ImportPurchaseOrder::class, 'import_purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoeItem::class, 'bill_of_entry_id');
    }

    /**
     * Recalculate duties and assessment value totals across all BOE items.
     */
    public function recalculateDuties(): void
    {
        $assessTotal = '0.0000';
        $cdTotal = '0.0000';
        $rdTotal = '0.0000';
        $sdTotal = '0.0000';
        $vatTotal = '0.0000';
        $atTotal = '0.0000';
        $aitTotal = '0.0000';
        $dutyTotal = '0.0000';

        foreach ($this->items as $item) {
            $assessTotal = bcadd($assessTotal, (string) $item->customs_assessment_value_bdt, 4);
            $cdTotal = bcadd($cdTotal, (string) $item->cd_amount_bdt, 4);
            $rdTotal = bcadd($rdTotal, (string) $item->rd_amount_bdt, 4);
            $sdTotal = bcadd($sdTotal, (string) $item->sd_amount_bdt, 4);
            $vatTotal = bcadd($vatTotal, (string) $item->vat_amount_bdt, 4);
            $atTotal = bcadd($atTotal, (string) $item->at_amount_bdt, 4);
            $aitTotal = bcadd($aitTotal, (string) $item->ait_amount_bdt, 4);
            $dutyTotal = bcadd($dutyTotal, (string) $item->total_duty_bdt, 4);
        }

        $dutyTotal = bcadd($dutyTotal, (string) $this->customs_fine_penalty, 4);

        $this->update([
            'customs_assessment_value_bdt' => $assessTotal,
            'customs_duty_cd' => $cdTotal,
            'regulatory_duty_rd' => $rdTotal,
            'supplementary_duty_sd' => $sdTotal,
            'vat_import' => $vatTotal,
            'advance_tax_at' => $atTotal,
            'advance_income_tax_ait' => $aitTotal,
            'total_customs_duties_bdt' => $dutyTotal,
        ]);
    }

    public function scopeOutOfCharge(Builder $query): Builder
    {
        return $query->where('assessment_status', 'out_of_charge');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('assessment_status', 'pending');
    }
}
