<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Procurement\Models\BillOfEntry;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use Carbon\CarbonImmutable;

class GenerateMushak61RegisterAction
{
    /**
     * Generate the statutory NBR Mushak 6.1 Purchase Register (ক্রয় হিসাব পুস্তক) for a tax period.
     *
     * @return array{
     *     tax_period: string,
     *     seller_name: string,
     *     seller_bin: string,
     *     total_local_purchases_bdt: string,
     *     total_local_vat_bdt: string,
     *     total_import_purchases_bdt: string,
     *     total_import_duties_bdt: string,
     *     total_inward_purchases_bdt: string,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    public function __invoke(string $taxPeriod): array
    {
        $startDate = CarbonImmutable::createFromFormat('Y-m', $taxPeriod)->startOfMonth();
        $endDate = $startDate->endOfMonth();

        $entries = [];
        $totalLocalPurchases = 0.00;
        $totalLocalVat = 0.00;
        $totalImportPurchases = 0.00;
        $totalImportDuties = 0.00;

        // 1. Domestic Purchases from LocalPurchaseOrder with supplier Mushak 6.3
        $localPos = LocalPurchaseOrder::with('supplier')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereNotNull('mushak_6_3_invoice_number')
            ->orderBy('order_date')
            ->get();

        foreach ($localPos as $po) {
            $subtotal = (float) $po->subtotal_bdt;
            $vat = (float) $po->vat_amount_bdt;
            $totalLocalPurchases += $subtotal;
            $totalLocalVat += $vat;

            $entries[] = [
                'type' => 'local_purchase',
                'date' => $po->order_date->format('Y-m-d'),
                'source_document' => 'Mushak 6.3: '.$po->mushak_6_3_invoice_number,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier?->name ?? 'Domestic Vendor',
                'supplier_bin' => $po->supplier?->bin_number ?? 'N/A',
                'description' => 'Local Procurement (PO #'.$po->po_number.')',
                'taxable_value_bdt' => number_format($subtotal, 4, '.', ''),
                'vat_amount_bdt' => number_format($vat, 4, '.', ''),
                'total_amount_bdt' => number_format($subtotal + $vat, 4, '.', ''),
            ];
        }

        // 2. Commercial Imports from BillOfEntry
        $boes = BillOfEntry::with('importPurchaseOrder.supplier')
            ->whereBetween('boe_date', [$startDate, $endDate])
            ->orderBy('boe_date')
            ->get();

        foreach ($boes as $boe) {
            $assess = (float) $boe->customs_assessment_value_bdt;
            $duties = (float) $boe->total_customs_duties_bdt;
            $totalImportPurchases += $assess;
            $totalImportDuties += $duties;

            $entries[] = [
                'type' => 'import_boe',
                'date' => $boe->boe_date->format('Y-m-d'),
                'source_document' => 'BOE: '.$boe->boe_number,
                'po_number' => $boe->importPurchaseOrder?->po_number ?? 'N/A',
                'supplier_name' => $boe->importPurchaseOrder?->supplier?->name ?? 'Overseas Manufacturer',
                'supplier_bin' => 'Customs Assessment (Station: '.$boe->customs_station.')',
                'description' => 'Commercial Import Clearance',
                'taxable_value_bdt' => number_format($assess, 4, '.', ''),
                'vat_amount_bdt' => number_format((float) $boe->vat_import, 4, '.', ''),
                'total_amount_bdt' => number_format($assess + $duties, 4, '.', ''),
            ];
        }

        // Sort chronologically by date
        usort($entries, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return [
            'tax_period' => $taxPeriod,
            'seller_name' => config('tax.seller_name', 'ElectroCommerce BD Ltd.'),
            'seller_bin' => config('tax.seller_bin', '001234567-0101'),
            'total_local_purchases_bdt' => number_format($totalLocalPurchases, 4, '.', ''),
            'total_local_vat_bdt' => number_format($totalLocalVat, 4, '.', ''),
            'total_import_purchases_bdt' => number_format($totalImportPurchases, 4, '.', ''),
            'total_import_duties_bdt' => number_format($totalImportDuties, 4, '.', ''),
            'total_inward_purchases_bdt' => number_format($totalLocalPurchases + $totalImportPurchases, 4, '.', ''),
            'entries' => $entries,
        ];
    }
}
