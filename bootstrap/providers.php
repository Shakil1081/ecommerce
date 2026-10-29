<?php

use App\Modules\Cart\Providers\CartServiceProvider;
use App\Modules\Common\Providers\CommonServiceProvider;
use App\Modules\Communication\Providers\CommunicationServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Logistics\Providers\LogisticsServiceProvider;
use App\Modules\Marketing\Providers\MarketingServiceProvider;
use App\Modules\Payment\Providers\PaymentServiceProvider;
use App\Modules\Procurement\Providers\ProcurementServiceProvider;
use App\Modules\TaxCompliance\Providers\TaxComplianceServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CommonServiceProvider::class,
    ProcurementServiceProvider::class,
    CartServiceProvider::class,
    InventoryServiceProvider::class,
    PaymentServiceProvider::class,
    LogisticsServiceProvider::class,
    CommunicationServiceProvider::class,
    MarketingServiceProvider::class,
    TaxComplianceServiceProvider::class,
];
