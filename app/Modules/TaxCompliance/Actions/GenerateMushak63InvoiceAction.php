<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\TaxCompliance\DTOs\Mushak63GenerationResultDto;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\Mushak63Item;
use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateMushak63InvoiceAction
{
    public function __construct(
        protected MushakSequenceService $sequenceService,
    ) {}

    /**
     * Generate an official statutory NBR Mushak 6.3 Tax Invoice for an order.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(Order $order, array $options = []): Mushak63GenerationResultDto
    {
        // 1. Check for existing invoice (idempotent)
        /** @var Mushak63Invoice|null $existing */
        $existing = Mushak63Invoice::where('order_id', $order->id)
            ->with('items')
            ->first();

        if ($existing) {
            return new Mushak63GenerationResultDto(
                invoice: $existing,
                invoiceNumber: $existing->invoice_number,
                subtotalExclusiveBdt: $existing->subtotal_exclusive_bdt,
                vatTotalBdt: $existing->vat_total_bdt,
                grandTotalBdt: $existing->grand_total_bdt,
                integrityHash: $existing->integrity_hash,
                isExisting: true,
            );
        }

        $now = CarbonImmutable::now();
        $taxPeriod = $options['tax_period'] ?? $now->format('Y-m');
        $issueDate = $options['issue_date'] ?? $now;

        $sellerName = $options['seller_name'] ?? config('tax.seller_name', 'ElectroCommerce BD Ltd.');
        $sellerBin = $options['seller_bin'] ?? config('tax.seller_bin', '001234567-0101');
        $sellerAddress = $options['seller_address'] ?? config('tax.seller_address', 'Plot 45, Gulshan Commercial Area, Dhaka-1212, Bangladesh');
        $taxCircle = $options['tax_circle'] ?? config('tax.tax_circle', 'Circle-15 (Gulshan)');
        $taxCommissionerate = $options['tax_commissionerate'] ?? config('tax.tax_commissionerate', 'Customs, Excise & VAT Commissionerate, Dhaka (North)');

        $pricesIncludeVat = (bool) ($options['prices_include_vat'] ?? true);
        $defaultVatRate = (float) ($options['vat_rate_percent'] ?? config('tax.default_vat_rate', 15.00));
        $defaultSdRate = (float) ($options['sd_rate_percent'] ?? 0.00);

        return DB::transaction(function () use (
            $order,
            $options,
            $issueDate,
            $taxPeriod,
            $sellerName,
            $sellerBin,
            $sellerAddress,
            $taxCircle,
            $taxCommissionerate,
            $pricesIncludeVat,
            $defaultVatRate,
            $defaultSdRate
        ): Mushak63GenerationResultDto {
            // 2. Generate statutory sequential invoice number under atomic lock
            $invoiceNumber = $this->sequenceService->generateNextSequence('6.3', $issueDate);

            // 3. Compute item tax breakdowns
            $itemsData = [];
            $totalSubtotalExclusive = 0.00;
            $totalSdAmount = 0.00;
            $totalVatAmount = 0.00;

            $order->loadMissing('items.product', 'items.variant');

            foreach ($order->items as $orderItem) {
                $qty = max(1, (int) $orderItem->quantity);
                $lineSubtotal = (float) $orderItem->subtotal_bdt;

                $itemVatRate = $options['item_vat_rate'][$orderItem->id] ?? $defaultVatRate;
                $itemSdRate = $options['item_sd_rate'][$orderItem->id] ?? $defaultSdRate;
                $isExempt = (bool) ($options['item_exempt'][$orderItem->id] ?? false);

                if ($isExempt) {
                    $itemVatRate = 0.00;
                    $itemSdRate = 0.00;
                }

                if ($pricesIncludeVat) {
                    $taxDivisor = 1.0 + ($itemVatRate / 100.0) + ($itemSdRate / 100.0);
                    $lineExclusive = round($lineSubtotal / $taxDivisor, 4);
                    $lineSd = round($lineExclusive * ($itemSdRate / 100.0), 4);
                    $lineVat = round($lineSubtotal - $lineExclusive - $lineSd, 4);
                    $lineTotalWithTax = $lineSubtotal;
                    $unitPriceExclusive = round($lineExclusive / $qty, 4);
                } else {
                    $lineExclusive = $lineSubtotal;
                    $unitPriceExclusive = round($lineExclusive / $qty, 4);
                    $lineSd = round($lineExclusive * ($itemSdRate / 100.0), 4);
                    $lineVat = round($lineExclusive * ($itemVatRate / 100.0), 4);
                    $lineTotalWithTax = round($lineExclusive + $lineSd + $lineVat, 4);
                }

                $totalSubtotalExclusive += $lineExclusive;
                $totalSdAmount += $lineSd;
                $totalVatAmount += $lineVat;

                $itemsData[] = [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'item_name' => $orderItem->product_name,
                    'sku' => $orderItem->sku,
                    'hs_code' => $orderItem->product?->hs_code ?? null,
                    'unit_of_measure' => 'PCS',
                    'quantity' => $qty,
                    'unit_price_exclusive_bdt' => number_format($unitPriceExclusive, 4, '.', ''),
                    'subtotal_exclusive_bdt' => number_format($lineExclusive, 4, '.', ''),
                    'sd_rate_percent' => number_format($itemSdRate, 2, '.', ''),
                    'sd_amount_bdt' => number_format($lineSd, 4, '.', ''),
                    'vat_rate_percent' => number_format($itemVatRate, 2, '.', ''),
                    'vat_amount_bdt' => number_format($lineVat, 4, '.', ''),
                    'total_with_tax_bdt' => number_format($lineTotalWithTax, 4, '.', ''),
                    'is_vat_exempt' => $isExempt,
                ];
            }

            $shippingFee = (float) $order->shipping_fee_bdt;
            $discountAmount = (float) $order->discount_amount_bdt;
            $grandTotal = (float) $order->total_bdt;

            // 4. Compute SHA-256 historical integrity fingerprint
            $hashPayload = [
                'invoice_number' => $invoiceNumber,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'seller_bin' => $sellerBin,
                'customer_name' => $order->customer_name,
                'subtotal_exclusive_bdt' => number_format($totalSubtotalExclusive, 4, '.', ''),
                'vat_total_bdt' => number_format($totalVatAmount, 4, '.', ''),
                'grand_total_bdt' => number_format($grandTotal, 4, '.', ''),
                'items' => array_map(fn (array $item): array => [
                    'sku' => $item['sku'],
                    'qty' => $item['quantity'],
                    'exclusive' => $item['subtotal_exclusive_bdt'],
                    'vat' => $item['vat_amount_bdt'],
                    'total' => $item['total_with_tax_bdt'],
                ], $itemsData),
            ];

            $integrityHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

            // 5. Persist Mushak 6.3 Invoice
            /** @var Mushak63Invoice $invoice */
            $invoice = Mushak63Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'tax_period' => $taxPeriod,
                'issue_date' => $issueDate,
                'seller_name' => $sellerName,
                'seller_bin' => $sellerBin,
                'seller_address' => $sellerAddress,
                'tax_circle' => $taxCircle,
                'tax_commissionerate' => $taxCommissionerate,
                'customer_name' => $order->customer_name,
                'customer_bin' => $options['customer_bin'] ?? null,
                'customer_phone' => $order->customer_phone,
                'customer_address' => $order->delivery_address,
                'destination_address' => $order->delivery_address,
                'vehicle_type_and_number' => $options['vehicle_type_and_number'] ?? null,
                'subtotal_exclusive_bdt' => number_format($totalSubtotalExclusive, 4, '.', ''),
                'supplementary_duty_total_bdt' => number_format($totalSdAmount, 4, '.', ''),
                'vat_total_bdt' => number_format($totalVatAmount, 4, '.', ''),
                'shipping_fee_bdt' => number_format($shippingFee, 4, '.', ''),
                'discount_amount_bdt' => number_format($discountAmount, 4, '.', ''),
                'grand_total_bdt' => number_format($grandTotal, 4, '.', ''),
                'is_exempt' => ($totalVatAmount == 0.00 && count($itemsData) > 0),
                'status' => 'issued',
                'integrity_hash' => $integrityHash,
                'notes' => $options['notes'] ?? null,
                'created_by_user_id' => $options['created_by_user_id'] ?? null,
            ]);

            // 6. Persist Line Items
            foreach ($itemsData as $itemData) {
                $itemData['mushak_6_3_invoice_id'] = $invoice->id;
                Mushak63Item::create($itemData);
            }

            // 7. Update order reference
            $order->updateQuietly(['mushak_6_3_number' => $invoiceNumber]);

            $invoice->load('items');

            return new Mushak63GenerationResultDto(
                invoice: $invoice,
                invoiceNumber: $invoiceNumber,
                subtotalExclusiveBdt: $invoice->subtotal_exclusive_bdt,
                vatTotalBdt: $invoice->vat_total_bdt,
                grandTotalBdt: $invoice->grand_total_bdt,
                integrityHash: $integrityHash,
                isExisting: false,
            );
        });
    }
}
