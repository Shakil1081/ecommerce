<?php

use App\Modules\Cart\Providers\CartServiceProvider;
use App\Modules\Common\Providers\CommonServiceProvider;
use App\Modules\Procurement\Providers\ProcurementServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CommonServiceProvider::class,
    ProcurementServiceProvider::class,
    CartServiceProvider::class,
];
