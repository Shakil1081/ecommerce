<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

final class VerifySupplierBinAction
{
    /**
     * Verify and normalize an NBR Business Identification Number (BIN).
     * Bangladesh NBR accepts 13-digit VAT Online registration or legacy 9-digit format.
     *
     * @return array{is_valid: bool, normalized_bin: ?string, format: ?string, error: ?string}
     */
    public function __invoke(?string $bin): array
    {
        if ($bin === null || trim($bin) === '') {
            return [
                'is_valid' => false,
                'normalized_bin' => null,
                'format' => null,
                'error' => 'BIN is required for verification.',
            ];
        }

        $normalized = (string) preg_replace('/[^0-9]/', '', $bin);

        if (strlen($normalized) === 13) {
            return [
                'is_valid' => true,
                'normalized_bin' => $normalized,
                'format' => '13_digit_online',
                'error' => null,
            ];
        }

        if (strlen($normalized) === 9) {
            return [
                'is_valid' => true,
                'normalized_bin' => $normalized,
                'format' => '9_digit_legacy',
                'error' => null,
            ];
        }

        return [
            'is_valid' => false,
            'normalized_bin' => $normalized,
            'format' => 'invalid',
            'error' => 'Invalid NBR BIN format. Must be a 9-digit or 13-digit registration number.',
        ];
    }

    /**
     * Verify and normalize a 12-digit e-TIN (Taxpayer Identification Number).
     *
     * @return array{is_valid: bool, normalized_tin: ?string, error: ?string}
     */
    public function verifyTin(?string $tin): array
    {
        if ($tin === null || trim($tin) === '') {
            return [
                'is_valid' => false,
                'normalized_tin' => null,
                'error' => 'TIN is required for verification.',
            ];
        }

        $normalized = (string) preg_replace('/[^0-9]/', '', $tin);

        if (strlen($normalized) === 12) {
            return [
                'is_valid' => true,
                'normalized_tin' => $normalized,
                'error' => null,
            ];
        }

        return [
            'is_valid' => false,
            'normalized_tin' => $normalized,
            'error' => 'Invalid e-TIN format. Must be a 12-digit taxpayer number.',
        ];
    }
}
