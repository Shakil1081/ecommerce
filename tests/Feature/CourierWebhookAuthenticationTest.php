<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Logistics\Models\CourierGatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourierWebhookAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createCourier(string $code, ?string $secret): void
    {
        CourierGatewaySetting::create([
            'code' => $code,
            'name' => ucfirst($code),
            'is_active' => true,
            'is_sandbox' => false,
            'credentials' => [],
            'webhook_secret' => $secret,
        ]);
    }

    public function test_production_rejects_webhooks_when_no_secret_is_configured(): void
    {
        $this->createCourier(CourierGatewaySetting::CODE_STEADFAST, null);
        $this->app['env'] = 'production';

        $this->postJson(route('webhooks.courier.steadfast'), [
            'consignment_id' => '123',
            'status' => 'delivered',
        ])->assertUnauthorized();
    }

    public function test_wrong_secret_is_rejected_and_correct_secret_is_accepted_past_auth(): void
    {
        $this->createCourier(CourierGatewaySetting::CODE_PATHAO, 'whsec_pathao_live');

        $this->withHeaders(['X-Pathao-Signature' => 'wrong'])
            ->postJson(route('webhooks.courier.pathao'), ['consignment_id' => 'X'])
            ->assertUnauthorized();

        // Authenticated: reaches consignment lookup (unknown consignment -> 404, not 401)
        $this->withHeaders(['Authorization' => 'Bearer whsec_pathao_live'])
            ->postJson(route('webhooks.courier.pathao'), ['consignment_id' => 'UNKNOWN'])
            ->assertNotFound();
    }
}
