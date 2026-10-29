<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\DTOs;

use App\Modules\TaxCompliance\Models\Mushak66Certificate;

final readonly class Mushak66GenerationResultDto
{
    public function __construct(
        public Mushak66Certificate $certificate,
        public string $certificateNumber,
        public string $supplierName,
        public string $supplierBin,
        public string $vdsDeductedAmountBdt,
        public string $netPaidToSupplierBdt,
        public bool $isExisting = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'certificate_number' => $this->certificateNumber,
            'supplier_name' => $this->supplierName,
            'supplier_bin' => $this->supplierBin,
            'vds_deducted_amount_bdt' => $this->vdsDeductedAmountBdt,
            'net_paid_to_supplier_bdt' => $this->netPaidToSupplierBdt,
            'is_existing' => $this->isExisting,
        ];
    }
}
