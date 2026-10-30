<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use Carbon\CarbonImmutable;

class GenerateMushak62RegisterAction
{
    /**
     * Generate the statutory NBR Mushak 6.2 / 6.2.1 Sales Register (বিক্রয় হিসাব পুস্তক / ব্যবসায়ী বিক্রয় হিসাব) for a tax period.
     *
     * @return array{
     *     tax_period: string,
     *     seller_name: string,
     *     seller_bin: string,
     *     total_sales_exclusive_bdt: string,
     *     total_sd_bdt: string,
     *     total_vat_bdt: string,
     *     total_sales_inclusive_bdt: string,
     *     invoices_count: int,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    public function __invoke(string $taxPeriod): array
    {
        $invoices = Mushak63Invoice::with('items')
            ->where('tax_period', $taxPeriod)
            ->where('status', '!=', 'cancelled')
            ->orderBy('issue_date')
            ->get();

        $entries = [];
        $totalSalesExclusive = 0.00;
        $totalSd = 0.00;
        $totalVat = 0.00;
        $totalSalesInclusive = 0.00;

        foreach ($invoices as $inv) {
            $exclusive = (float) $inv->subtotal_exclusive_bdt;
            $sd = (float) $inv->supplementary_duty_total_bdt;
            $vat = (float) $inv->vat_total_bdt;
            $grand = (float) $inv->grand_total_bdt;

            $totalSalesExclusive += $exclusive;
            $totalSd += $sd;
            $totalVat += $vat;
            $totalSalesInclusive += $grand;

            $itemsSummary = $inv->items->map(fn ($item) => $item->item_name.' (x'.$item->quantity.')')->implode(', ');

            $entries[] = [
                'date' => CarbonImmutable::parse($inv->issue_date)->format('Y-m-d H:i'),
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer_name,
                'customer_bin' => $inv->customer_bin ?? 'Consumer / N/A',
                'customer_phone' => $inv->customer_phone,
                'destination_address' => $inv->destination_address,
                'items_summary' => $itemsSummary,
                'subtotal_exclusive_bdt' => number_format($exclusive, 4, '.', ''),
                'sd_amount_bdt' => number_format($sd, 4, '.', ''),
                'vat_amount_bdt' => number_format($vat, 4, '.', ''),
                'grand_total_bdt' => number_format($grand, 4, '.', ''),
                'status' => $inv->status,
            ];
        }

        return [
            'tax_period' => $taxPeriod,
            'seller_name' => config('tax.seller_name', 'ElectroCommerce BD Ltd.'),
            'seller_bin' => config('tax.seller_bin', '001234567-0101'),
            'total_sales_exclusive_bdt' => number_format($totalSalesExclusive, 4, '.', ''),
            'total_sd_bdt' => number_format($totalSd, 4, '.', ''),
            'total_vat_bdt' => number_format($totalVat, 4, '.', ''),
            'total_sales_inclusive_bdt' => number_format($totalSalesInclusive, 4, '.', ''),
            'invoices_count' => count($entries),
            'entries' => $entries,
        ];
    }
}
