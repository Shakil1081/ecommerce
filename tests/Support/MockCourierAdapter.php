<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Logistics\Contracts\CourierAdapterContract;
use App\Modules\Logistics\DTOs\CourierBookingResultDto;
use App\Modules\Logistics\DTOs\CourierConnectionTestResultDto;
use App\Modules\Logistics\DTOs\CourierTrackingResultDto;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;

class MockCourierAdapter implements CourierAdapterContract
{
    public function __construct(
        protected string $code = 'mock_courier',
        protected string $name = 'Mock Courier Service',
        protected bool $shouldFail = false,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function bookConsignment(Order $order, array $options = []): CourierBookingResultDto
    {
        if ($this->shouldFail) {
            return CourierBookingResultDto::failed('Mock booking failed: invalid recipient address');
        }

        return CourierBookingResultDto::success(
            consignmentId: 'MOCK-CID-12345',
            trackingCode: 'TRK-987654321',
            status: 'booked',
            deliveryFeeBdt: '70.0000',
            rawPayload: ['order_id' => $order->id, 'mock' => true],
        );
    }

    public function trackConsignment(string $trackingCodeOrId): CourierTrackingResultDto
    {
        if ($this->shouldFail) {
            return CourierTrackingResultDto::failed('Mock tracking failed: parcel not found');
        }

        return CourierTrackingResultDto::success(
            status: 'in_transit',
            statusDetails: 'Parcel is at Dhaka Sorting Hub',
            currentLocation: 'Dhaka Hub',
            deliveredAt: null,
            events: [
                ['time' => '2026-10-29 10:00:00', 'status' => 'picked_up', 'note' => 'Picked up from merchant'],
                ['time' => '2026-10-29 14:00:00', 'status' => 'in_transit', 'note' => 'Arrived at hub'],
            ],
            rawPayload: ['tracking_code' => $trackingCodeOrId],
        );
    }

    public function testConnection(?CourierGatewaySetting $setting = null): CourierConnectionTestResultDto
    {
        if ($this->shouldFail) {
            return CourierConnectionTestResultDto::failed(
                message: 'Mock connection failed: Invalid API credentials',
                latencyMs: 12.5,
            );
        }

        return CourierConnectionTestResultDto::success(
            message: 'Mock connection successful. Authorized with Mock Courier API.',
            latencyMs: 15.3,
            details: ['balance' => 5000],
        );
    }
}
