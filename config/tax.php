<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bangladesh NBR VAT & Tax Compliance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for statutory NBR documents under the Bangladesh Value
    | Added Tax and Supplementary Duty Act 2012 and Income Tax Act 2023.
    |
    */

    'seller_name' => env('NBR_SELLER_NAME', 'ElectroCommerce BD Ltd.'),
    'seller_bin' => env('NBR_SELLER_BIN', '001234567-0101'),
    'seller_address' => env('NBR_SELLER_ADDRESS', 'Plot 45, Gulshan Commercial Area, Dhaka-1212, Bangladesh'),
    'tax_circle' => env('NBR_TAX_CIRCLE', 'Circle-15 (Gulshan)'),
    'tax_commissionerate' => env('NBR_TAX_COMMISSIONERATE', 'Customs, Excise & VAT Commissionerate, Dhaka (North)'),

    'default_vat_rate' => (float) env('NBR_DEFAULT_VAT_RATE', 15.00),
    'retail_trader_vat_rate' => (float) env('NBR_RETAIL_TRADER_VAT_RATE', 5.00),

    'vds_applicable' => (bool) env('NBR_VDS_APPLICABLE', true),
];
