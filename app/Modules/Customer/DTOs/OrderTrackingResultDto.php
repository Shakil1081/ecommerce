<?php

declare(strict_types=1);

namespace App\Modules\Customer\DTOs;

final readonly class OrderTrackingResultDto
{
    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $milestones
     * @param  array<string, mixed>|null  $courier
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $financials
     * @param  array<string, mixed>  $shippingAddress
     */
    public function __construct(
        public array $order,
        public array $milestones,
        public ?array $courier,
        public array $items,
        public array $financials,
        public array $shippingAddress,
    ) {}

    /**
     * Convert DTO to array for API/Inertia props.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order' => $this->order,
            'milestones' => $this->milestones,
            'courier' => $this->courier,
            'items' => $this->items,
            'financials' => $this->financials,
            'shipping_address' => $this->shippingAddress,
        ];
    }
}
