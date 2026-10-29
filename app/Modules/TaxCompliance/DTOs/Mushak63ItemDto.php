<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\DTOs;

final readonly class Mushak63ItemDto
{
    public function __construct(
        public string $itemName,
        public string $sku,
        public ?string $hsCode,
        public string $unitOfMeasure,
        public int $quantity,
        public string $unitPriceExclusiveBdt,
        public string $subtotalExclusiveBdt,
        public string $sdRatePercent,
        public string $sdAmountBdt,
        public string $vatRatePercent,
        public string $vatAmountBdt,
        public string $totalWithTaxBdt,
        public bool $isVatExempt,
        public ?int $orderItemId = null,
        public ?string $productId = null,
        public ?string $productVariantId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_name' => $this->itemName,
            'sku' => $this->sku,
            'hs_code' => $this->hsCode,
            'unit_of_measure' => $this->unitOfMeasure,
            'quantity' => $this->quantity,
            'unit_price_exclusive_bdt' => $this->unitPriceExclusiveBdt,
            'subtotal_exclusive_bdt' => $this->subtotalExclusiveBdt,
            'sd_rate_percent' => $this->sdRatePercent,
            'sd_amount_bdt' => $this->sdAmountBdt,
            'vat_rate_percent' => $this->vatRatePercent,
            'vat_amount_bdt' => $this->vatAmountBdt,
            'total_with_tax_bdt' => $this->totalWithTaxBdt,
            'is_vat_exempt' => $this->isVatExempt,
            'order_item_id' => $this->orderItemId,
            'product_id' => $this->productId,
            'product_variant_id' => $this->productVariantId,
        ];
    }
}
