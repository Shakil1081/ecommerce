<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Contracts;

use App\Modules\Logistics\DTOs\CourierBookingResultDto;
use App\Modules\Logistics\DTOs\CourierConnectionTestResultDto;
use App\Modules\Logistics\DTOs\CourierTrackingResultDto;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;

interface CourierAdapterContract
{
    /**
     * Unique courier code (e.g. steadfast, pathao, redx, paperfly, sundarban).
     */
    public function code(): string;

    /**
     * Human-readable courier name.
     */
    public function name(): string;

    /**
     * Dispatch and book a consignment for an order with this courier.
     *
     * @param  array<string, mixed>  $options
     */
    public function bookConsignment(Order $order, array $options = []): CourierBookingResultDto;

    /**
     * Track an active consignment by tracking code or consignment ID.
     */
    public function trackConsignment(string $trackingCodeOrId): CourierTrackingResultDto;

    /**
     * Test API connection with the courier gateway.
     */
    public function testConnection(?CourierGatewaySetting $setting = null): CourierConnectionTestResultDto;
}
