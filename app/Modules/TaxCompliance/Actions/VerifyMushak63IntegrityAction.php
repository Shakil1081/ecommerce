<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\TaxCompliance\Models\Mushak63Invoice;

class VerifyMushak63IntegrityAction
{
    /**
     * Recompute and verify the SHA-256 statutory integrity hash for a Mushak 6.3 invoice.
     */
    public function __invoke(Mushak63Invoice $invoice): bool
    {
        $invoice->loadMissing('order', 'items');

        $itemsData = $invoice->items->map(fn ($item): array => [
            'sku' => $item->sku,
            'qty' => $item->quantity,
            'exclusive' => number_format((float) $item->subtotal_exclusive_bdt, 4, '.', ''),
            'vat' => number_format((float) $item->vat_amount_bdt, 4, '.', ''),
            'total' => number_format((float) $item->total_with_tax_bdt, 4, '.', ''),
        ])->values()->all();

        $hashPayload = [
            'invoice_number' => $invoice->invoice_number,
            'order_id' => $invoice->order_id,
            'order_number' => $invoice->order->order_number,
            'seller_bin' => $invoice->seller_bin,
            'customer_name' => $invoice->customer_name,
            'subtotal_exclusive_bdt' => number_format((float) $invoice->subtotal_exclusive_bdt, 4, '.', ''),
            'vat_total_bdt' => number_format((float) $invoice->vat_total_bdt, 4, '.', ''),
            'grand_total_bdt' => number_format((float) $invoice->grand_total_bdt, 4, '.', ''),
            'items' => $itemsData,
        ];

        $recomputedHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

        return hash_equals($invoice->integrity_hash, $recomputedHash);
    }
}
