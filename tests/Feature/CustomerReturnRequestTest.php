<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsStorefrontFixtures;
use Tests\TestCase;

final class CustomerReturnRequestTest extends TestCase
{
    use BuildsStorefrontFixtures;
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildStorefrontFixtures();
        $this->customer = User::factory()->create();
    }

    public function test_customer_can_request_return_for_delivered_order(): void
    {
        $order = $this->deliveredOrder(daysAgo: 2);
        $itemId = $order->items->first()->id;

        $this->actingAs($this->customer)
            ->post("/account/orders/{$order->order_number}/returns", [
                'reason' => 'defective',
                'notes' => 'Zip is broken',
                'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $rma = ReturnMerchandiseAuthorization::query()->with('items')->firstOrFail();
        $this->assertSame('requested', $rma->status);
        $this->assertSame($this->customer->id, $rma->user_id);
        $this->assertSame('2500.0000', $rma->refund_amount_bdt);
        $this->assertSame(1, (int) $rma->items->first()->quantity_requested);

        // Page now shows only the remaining returnable unit
        $this->actingAs($this->customer)->get("/account/orders/{$order->order_number}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Account/OrderShow')
                ->where('order.items.0.returnable_quantity', 1)
                ->has('returns', 1));
    }

    public function test_cannot_return_more_units_than_remain(): void
    {
        $order = $this->deliveredOrder(daysAgo: 1);
        $itemId = $order->items->first()->id;
        $url = "/account/orders/{$order->order_number}/returns";

        $this->actingAs($this->customer)->post($url, [
            'reason' => 'changed_mind',
            'items' => [['order_item_id' => $itemId, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->customer)->post($url, [
            'reason' => 'changed_mind',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(1, ReturnMerchandiseAuthorization::query()->count());
    }

    public function test_return_window_and_delivery_status_are_enforced(): void
    {
        $expired = $this->deliveredOrder(daysAgo: 10);
        $pending = $this->createOrder(['user_id' => $this->customer->id, 'status' => Order::STATUS_PENDING]);

        foreach ([$expired, $pending] as $order) {
            $this->actingAs($this->customer)->post("/account/orders/{$order->order_number}/returns", [
                'reason' => 'defective',
                'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
            ])->assertSessionHasErrors('items');
        }

        $this->assertSame(0, ReturnMerchandiseAuthorization::query()->count());
    }

    public function test_customer_cannot_return_someone_elses_order(): void
    {
        $order = $this->deliveredOrder(daysAgo: 1);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post("/account/orders/{$order->order_number}/returns", [
            'reason' => 'defective',
            'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        ])->assertNotFound();
    }

    private function deliveredOrder(int $daysAgo): Order
    {
        return $this->createOrder([
            'user_id' => $this->customer->id,
            'status' => Order::STATUS_DELIVERED,
            'delivered_at' => now()->subDays($daysAgo),
        ]);
    }
}
