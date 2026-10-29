<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bill_of_entry_id
 * @property int|null $import_po_item_id
 * @property string $product_variant_id
 * @property string $hs_code
 * @property string $item_description
 * @property int $quantity
 * @property string $customs_assessment_value_bdt
 * @property string $cd_rate_percent
 * @property string $cd_amount_bdt
 * @property string $rd_rate_percent
 * @property string $rd_amount_bdt
 * @property string $sd_rate_percent
 * @property string $sd_amount_bdt
 * @property string $vat_rate_percent
 * @property string $vat_amount_bdt
 * @property string $at_rate_percent
 * @property string $at_amount_bdt
 * @property string $ait_rate_percent
 * @property string $ait_amount_bdt
 * @property string $total_duty_bdt
 * @property BillOfEntry $billOfEntry
 * @property ImportPoItem|null $importPoItem
 * @property ProductVariant $variant
 */
class BoeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_of_entry_id',
        'import_po_item_id',
        'product_variant_id',
        'hs_code',
        'item_description',
        'quantity',
        'customs_assessment_value_bdt',
        'cd_rate_percent',
        'cd_amount_bdt',
        'rd_rate_percent',
        'rd_amount_bdt',
        'sd_rate_percent',
        'sd_amount_bdt',
        'vat_rate_percent',
        'vat_amount_bdt',
        'at_rate_percent',
        'at_amount_bdt',
        'ait_rate_percent',
        'ait_amount_bdt',
        'total_duty_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'customs_assessment_value_bdt' => 'decimal:4',
            'cd_rate_percent' => 'decimal:2',
            'cd_amount_bdt' => 'decimal:4',
            'rd_rate_percent' => 'decimal:2',
            'rd_amount_bdt' => 'decimal:4',
            'sd_rate_percent' => 'decimal:2',
            'sd_amount_bdt' => 'decimal:4',
            'vat_rate_percent' => 'decimal:2',
            'vat_amount_bdt' => 'decimal:4',
            'at_rate_percent' => 'decimal:2',
            'at_amount_bdt' => 'decimal:4',
            'ait_rate_percent' => 'decimal:2',
            'ait_amount_bdt' => 'decimal:4',
            'total_duty_bdt' => 'decimal:4',
        ];
    }

    public function billOfEntry(): BelongsTo
    {
        return $this->belongsTo(BillOfEntry::class, 'bill_of_entry_id');
    }

    public function importPoItem(): BelongsTo
    {
        return $this->belongsTo(ImportPoItem::class, 'import_po_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Compute statutory duties in accordance with Bangladesh Customs & NBR calculation methodology.
     */
    public function computeTariffs(): void
    {
        $av = (string) $this->customs_assessment_value_bdt;

        // CD: Assessment Value * (CD% / 100)
        $cdAmount = bcdiv(bcmul($av, (string) $this->cd_rate_percent, 6), '100', 4);

        // RD: Assessment Value * (RD% / 100)
        $rdAmount = bcdiv(bcmul($av, (string) $this->rd_rate_percent, 6), '100', 4);

        // Base for SD = AV + CD + RD
        $sdBase = bcadd(bcadd($av, $cdAmount, 4), $rdAmount, 4);
        $sdAmount = bcdiv(bcmul($sdBase, (string) $this->sd_rate_percent, 6), '100', 4);

        // Base for VAT = AV + CD + RD + SD
        $vatBase = bcadd($sdBase, $sdAmount, 4);
        $vatAmount = bcdiv(bcmul($vatBase, (string) $this->vat_rate_percent, 6), '100', 4);

        // AT (Advance Tax 5%): Assessment Value * (AT% / 100)
        $atAmount = bcdiv(bcmul($av, (string) $this->at_rate_percent, 6), '100', 4);

        // AIT (Advance Income Tax 5%): Assessment Value * (AIT% / 100)
        $aitAmount = bcdiv(bcmul($av, (string) $this->ait_rate_percent, 6), '100', 4);

        // Total customs tariff = CD + RD + SD + VAT + AT + AIT
        $totalDuty = bcadd(
            bcadd(
                bcadd(bcadd(bcadd($cdAmount, $rdAmount, 4), $sdAmount, 4), $vatAmount, 4),
                $atAmount,
                4
            ),
            $aitAmount,
            4
        );

        $this->update([
            'cd_amount_bdt' => $cdAmount,
            'rd_amount_bdt' => $rdAmount,
            'sd_amount_bdt' => $sdAmount,
            'vat_amount_bdt' => $vatAmount,
            'at_amount_bdt' => $atAmount,
            'ait_amount_bdt' => $aitAmount,
            'total_duty_bdt' => $totalDuty,
        ]);
    }
}
