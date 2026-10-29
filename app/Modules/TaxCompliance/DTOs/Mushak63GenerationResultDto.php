<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\DTOs;

use App\Modules\TaxCompliance\Models\Mushak63Invoice;

final readonly class Mushak63GenerationResultDto
{
    public function __construct(
        public Mushak63Invoice $invoice,
        public string $invoiceNumber,
        public string $subtotalExclusiveBdt,
        public string $vatTotalBdt,
        public string $grandTotalBdt,
        public string $integrityHash,
        public bool $isExisting = false,
    ) {}
}
