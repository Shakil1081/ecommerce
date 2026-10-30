<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\TaxCompliance\Models\Mushak68DebitNote;
use App\Modules\TaxCompliance\Models\Mushak68Item;
use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IssueMushak68DebitNoteAction
{
    public function __construct(
        protected MushakSequenceService $sequenceService,
    ) {}

    /**
     * Issue a statutory NBR Mushak 6.8 Debit Note (ক্রয় ফেরত চালানপত্র - বৃদ্ধিকারী সমন্বয় দলিল)
     * for returning procured goods back to a vendor.
     *
     * @param  array<int, array{product_id?: ?string, product_variant_id?: ?string, item_name: string, sku: string, quantity: int, unit_price_exclusive_bdt: float, vat_rate_percent?: float}>  $returnedItems
     * @param  array<string, mixed>  $options
     */
    public function __invoke(
        Supplier $supplier,
        array $returnedItems,
        string $originalInvoiceNumber,
        string $originalInvoiceDate,
        string $reasonForReturn,
        ?LocalPurchaseOrder $po = null,
        array $options = []
    ): Mushak68DebitNote {
        if (empty($returnedItems)) {
            throw new InvalidArgumentException('Debit note must include at least one returned item.');
        }

        $now = CarbonImmutable::now();
        $issueDate = isset($options['issue_date']) ? CarbonImmutable::parse($options['issue_date']) : $now;
        $taxPeriod = $options['tax_period'] ?? $issueDate->format('Y-m');

        $buyerName = $options['buyer_name'] ?? config('tax.seller_name', 'ElectroCommerce BD Ltd.');
        $buyerBin = $options['buyer_bin'] ?? config('tax.seller_bin', '001234567-0101');
        $buyerAddress = $options['buyer_address'] ?? config('tax.seller_address', 'Plot 45, Gulshan Commercial Area, Dhaka-1212, Bangladesh');

        return DB::transaction(function () use (
            $supplier,
            $returnedItems,
            $originalInvoiceNumber,
            $originalInvoiceDate,
            $reasonForReturn,
            $po,
            $issueDate,
            $taxPeriod,
            $buyerName,
            $buyerBin,
            $buyerAddress,
            $options
        ): Mushak68DebitNote {
            // 1. Generate sequential M6.8-YYYYMM-XXXXXX debit note number
            $debitNoteNumber = $this->sequenceService->generateNextSequence('6.8', $issueDate);

            $itemsData = [];
            $totalReturnedExclusive = 0.00;
            $totalReturnedSd = 0.00;
            $totalReturnedVat = 0.00;
            $totalDebitAmount = 0.00;

            foreach ($returnedItems as $retItem) {
                $qty = (int) $retItem['quantity'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException("Returned quantity must be positive for SKU: {$retItem['sku']}");
                }

                $unitExclusive = (float) $retItem['unit_price_exclusive_bdt'];
                $lineExclusive = round($unitExclusive * $qty, 4);

                $vatRate = (float) ($retItem['vat_rate_percent'] ?? 15.00);
                $lineVat = round($lineExclusive * ($vatRate / 100.0), 4);
                $lineDebit = round($lineExclusive + $lineVat, 4);

                $totalReturnedExclusive += $lineExclusive;
                $totalReturnedVat += $lineVat;
                $totalDebitAmount += $lineDebit;

                $itemsData[] = [
                    'product_id' => $retItem['product_id'] ?? null,
                    'product_variant_id' => $retItem['product_variant_id'] ?? null,
                    'item_name' => $retItem['item_name'],
                    'sku' => $retItem['sku'],
                    'quantity_returned' => $qty,
                    'unit_price_exclusive_bdt' => number_format($unitExclusive, 4, '.', ''),
                    'subtotal_exclusive_bdt' => number_format($lineExclusive, 4, '.', ''),
                    'vat_rate_percent' => number_format($vatRate, 2, '.', ''),
                    'vat_amount_bdt' => number_format($lineVat, 4, '.', ''),
                    'total_debit_bdt' => number_format($lineDebit, 4, '.', ''),
                ];
            }

            // 2. Compute SHA-256 historical integrity fingerprint
            $hashPayload = [
                'debit_note_number' => $debitNoteNumber,
                'supplier_bin' => $supplier->bin_number,
                'original_invoice_number' => $originalInvoiceNumber,
                'returned_exclusive_bdt' => number_format($totalReturnedExclusive, 4, '.', ''),
                'returned_vat_bdt' => number_format($totalReturnedVat, 4, '.', ''),
                'total_debit_amount_bdt' => number_format($totalDebitAmount, 4, '.', ''),
                'items' => array_map(fn (array $i): array => [
                    'sku' => $i['sku'],
                    'qty' => $i['quantity_returned'],
                    'vat' => $i['vat_amount_bdt'],
                    'debit' => $i['total_debit_bdt'],
                ], $itemsData),
            ];

            $integrityHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

            /** @var Mushak68DebitNote $debitNote */
            $debitNote = Mushak68DebitNote::create([
                'debit_note_number' => $debitNoteNumber,
                'tax_period' => $taxPeriod,
                'issue_date' => $issueDate,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_bin' => (string) ($supplier->bin_number ?? '000000000-0000'),
                'supplier_address' => $supplier->address ?? 'Bangladesh',
                'buyer_name' => $buyerName,
                'buyer_bin' => $buyerBin,
                'buyer_address' => $buyerAddress,
                'local_purchase_order_id' => $po?->id,
                'original_invoice_number' => $originalInvoiceNumber,
                'original_invoice_date' => CarbonImmutable::parse($originalInvoiceDate),
                'reason_for_return' => $reasonForReturn,
                'returned_value_exclusive_bdt' => number_format($totalReturnedExclusive, 4, '.', ''),
                'returned_sd_amount_bdt' => number_format($totalReturnedSd, 4, '.', ''),
                'returned_vat_amount_bdt' => number_format($totalReturnedVat, 4, '.', ''),
                'total_debit_amount_bdt' => number_format($totalDebitAmount, 4, '.', ''),
                'is_adjusted_in_mushak_9_1' => false,
                'status' => 'issued',
                'integrity_hash' => $integrityHash,
                'created_by_user_id' => $options['created_by_user_id'] ?? null,
            ]);

            foreach ($itemsData as $itemData) {
                $itemData['mushak_6_8_debit_note_id'] = $debitNote->id;
                Mushak68Item::create($itemData);
            }

            $debitNote->load('items');

            return $debitNote;
        });
    }
}
