<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\Mushak63Item;
use App\Modules\TaxCompliance\Models\Mushak67CreditNote;
use App\Modules\TaxCompliance\Models\Mushak67Item;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IssueMushak67CreditNoteAction
{
    public function __construct(
        protected MushakSequenceService $sequenceService,
        protected GenerateMushak63InvoiceAction $generateInvoiceAction,
    ) {}

    /**
     * Issue a statutory NBR Mushak 6.7 Credit Note (বিক্রয় ফেরত চালানপত্র - হ্রাসকারী সমন্বয় দলিল)
     * linking to the original Mushak 6.3 Tax Invoice.
     *
     * @param  array<int, array{order_item_id: int, quantity: int}>  $returnedItems
     * @param  array<string, mixed>  $options
     */
    public function __invoke(
        Order $order,
        array $returnedItems,
        string $reasonForReturn,
        ?ReturnMerchandiseAuthorization $rma = null,
        array $options = []
    ): Mushak67CreditNote {
        if (empty($returnedItems)) {
            throw new InvalidArgumentException('Credit note must include at least one returned item.');
        }

        // 1. Ensure original Mushak 6.3 invoice exists
        $order->loadMissing('mushak63Invoice.items');
        /** @var Mushak63Invoice|null $invoice */
        $invoice = $order->mushak63Invoice;

        if (! $invoice) {
            $invoiceResult = ($this->generateInvoiceAction)($order);
            $invoice = $invoiceResult->invoice;
        }

        $now = CarbonImmutable::now();
        $issueDate = isset($options['issue_date']) ? CarbonImmutable::parse($options['issue_date']) : $now;
        $taxPeriod = $options['tax_period'] ?? $issueDate->format('Y-m');

        return DB::transaction(function () use (
            $order,
            $invoice,
            $returnedItems,
            $reasonForReturn,
            $rma,
            $issueDate,
            $taxPeriod,
            $options
        ): Mushak67CreditNote {
            // 2. Generate sequential M6.7-YYYYMM-XXXXXX credit note number
            $creditNoteNumber = $this->sequenceService->generateNextSequence('6.7', $issueDate);

            $itemsData = [];
            $totalReturnedExclusive = 0.00;
            $totalReturnedSd = 0.00;
            $totalReturnedVat = 0.00;
            $totalCreditAmount = 0.00;

            foreach ($returnedItems as $retItem) {
                $orderItemId = (int) $retItem['order_item_id'];
                $returnQty = (int) $retItem['quantity'];

                /** @var Mushak63Item|null $invoiceItem */
                $invoiceItem = $invoice->items->firstWhere('order_item_id', $orderItemId);

                if (! $invoiceItem) {
                    throw new InvalidArgumentException("Item {$orderItemId} was not found on original Mushak 6.3 invoice {$invoice->invoice_number}.");
                }

                if ($returnQty <= 0 || $returnQty > (int) $invoiceItem->quantity) {
                    throw new InvalidArgumentException("Returned quantity {$returnQty} exceeds invoiced quantity {$invoiceItem->quantity} for {$invoiceItem->item_name}.");
                }

                $unitExclusive = (float) $invoiceItem->unit_price_exclusive_bdt;
                $lineExclusive = round($unitExclusive * $returnQty, 4);

                $vatRate = (float) $invoiceItem->vat_rate_percent;
                $sdRate = (float) $invoiceItem->sd_rate_percent;

                $lineSd = round($lineExclusive * ($sdRate / 100.0), 4);
                $lineVat = round($lineExclusive * ($vatRate / 100.0), 4);
                $lineTotalCredit = round($lineExclusive + $lineSd + $lineVat, 4);

                $totalReturnedExclusive += $lineExclusive;
                $totalReturnedSd += $lineSd;
                $totalReturnedVat += $lineVat;
                $totalCreditAmount += $lineTotalCredit;

                $itemsData[] = [
                    'mushak_6_3_item_id' => $invoiceItem->id,
                    'product_id' => $invoiceItem->product_id,
                    'product_variant_id' => $invoiceItem->product_variant_id,
                    'item_name' => $invoiceItem->item_name,
                    'sku' => $invoiceItem->sku,
                    'quantity_returned' => $returnQty,
                    'unit_price_exclusive_bdt' => number_format($unitExclusive, 4, '.', ''),
                    'subtotal_exclusive_bdt' => number_format($lineExclusive, 4, '.', ''),
                    'sd_rate_percent' => number_format($sdRate, 2, '.', ''),
                    'sd_amount_bdt' => number_format($lineSd, 4, '.', ''),
                    'vat_rate_percent' => number_format($vatRate, 2, '.', ''),
                    'vat_amount_bdt' => number_format($lineVat, 4, '.', ''),
                    'total_credit_bdt' => number_format($lineTotalCredit, 4, '.', ''),
                ];
            }

            // 3. Compute SHA-256 historical integrity fingerprint
            $hashPayload = [
                'credit_note_number' => $creditNoteNumber,
                'order_id' => $order->id,
                'mushak_6_3_invoice' => $invoice->invoice_number,
                'returned_exclusive_bdt' => number_format($totalReturnedExclusive, 4, '.', ''),
                'returned_vat_bdt' => number_format($totalReturnedVat, 4, '.', ''),
                'total_credit_bdt' => number_format($totalCreditAmount, 4, '.', ''),
                'items' => array_map(fn (array $i): array => [
                    'sku' => $i['sku'],
                    'qty' => $i['quantity_returned'],
                    'vat' => $i['vat_amount_bdt'],
                    'credit' => $i['total_credit_bdt'],
                ], $itemsData),
            ];

            $integrityHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

            // 4. Create Mushak 6.7 Credit Note
            /** @var Mushak67CreditNote $creditNote */
            $creditNote = Mushak67CreditNote::create([
                'credit_note_number' => $creditNoteNumber,
                'tax_period' => $taxPeriod,
                'issue_date' => $issueDate,
                'order_id' => $order->id,
                'mushak_6_3_invoice_id' => $invoice->id,
                'mushak_6_3_invoice_number' => $invoice->invoice_number,
                'mushak_6_3_invoice_date' => $invoice->issue_date,
                'rma_id' => $rma?->id,
                'seller_name' => $invoice->seller_name,
                'seller_bin' => $invoice->seller_bin,
                'seller_address' => $invoice->seller_address,
                'customer_name' => $invoice->customer_name,
                'customer_bin' => $invoice->customer_bin,
                'customer_phone' => $invoice->customer_phone,
                'customer_address' => $invoice->customer_address,
                'reason_for_return' => $reasonForReturn,
                'original_invoice_total_bdt' => $invoice->grand_total_bdt,
                'returned_value_exclusive_bdt' => number_format($totalReturnedExclusive, 4, '.', ''),
                'returned_sd_amount_bdt' => number_format($totalReturnedSd, 4, '.', ''),
                'returned_vat_amount_bdt' => number_format($totalReturnedVat, 4, '.', ''),
                'total_credit_amount_bdt' => number_format($totalCreditAmount, 4, '.', ''),
                'is_adjusted_in_mushak_9_1' => false,
                'status' => 'issued',
                'integrity_hash' => $integrityHash,
                'created_by_user_id' => $options['created_by_user_id'] ?? null,
            ]);

            // 5. Persist Line Items
            foreach ($itemsData as $itemData) {
                $itemData['mushak_6_7_credit_note_id'] = $creditNote->id;
                Mushak67Item::create($itemData);
            }

            $creditNote->load('items');

            return $creditNote;
        });
    }
}
