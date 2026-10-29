<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\DTOs;

use App\Modules\TaxCompliance\Models\Mushak65Challan;

final readonly class Mushak65GenerationResultDto
{
    public function __construct(
        public Mushak65Challan $challan,
        public string $challanNumber,
        public string $sourceWarehouseName,
        public string $destinationWarehouseName,
        public int $totalQuantity,
        public string $totalValuationBdt,
        public bool $isExisting = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'challan_number' => $this->challanNumber,
            'source_warehouse_name' => $this->sourceWarehouseName,
            'destination_warehouse_name' => $this->destinationWarehouseName,
            'total_quantity' => $this->totalQuantity,
            'total_valuation_bdt' => $this->totalValuationBdt,
            'is_existing' => $this->isExisting,
        ];
    }
}
