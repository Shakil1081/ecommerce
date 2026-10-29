<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Contracts;

use App\Modules\Logistics\DTOs\CourierDeliveryProfileDto;

interface CourierDeliveryIntelligenceContract
{
    /**
     * Retrieve courier delivery completion and return history for a customer phone number.
     */
    public function checkCustomerDeliveryHistory(string $phone): CourierDeliveryProfileDto;

    /**
     * Return the unique courier identifier (e.g. 'steadfast', 'pathao').
     */
    public function getCourierCode(): string;
}
