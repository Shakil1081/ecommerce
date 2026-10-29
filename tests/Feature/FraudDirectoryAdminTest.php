<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Actions\CheckCustomerFraudListAction;
use App\Modules\Customer\Models\CustomerFraudList;
use App\Modules\Order\Actions\CalculateOrderFraudRiskScoreAction;
use App\Modules\Order\DTOs\OrderFraudRiskAssessmentDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class FraudDirectoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_view_fraud_directory_page(): void
    {
        CustomerFraudList::create([
            'type' => CustomerFraudList::TYPE_BLACKLIST,
            'phone_number' => '01711223344',
            'customer_name' => 'Abusive Shopper',
            'reason' => 'Repeated courier parcel returns',
            'blocked_by_user_id' => $this->admin->id,
        ]);

        CustomerFraudList::create([
            'type' => CustomerFraudList::TYPE_WHITELIST,
            'phone_number' => '01899887766',
            'customer_name' => 'Trusted VIP',
            'reason' => 'Corporate trade account',
            'blocked_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/fraud-directory');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Fraud/FraudDirectory')
            ->has('entries.data', 1)
            ->where('counts.blacklist', 1)
            ->where('counts.whitelist', 1)
            ->where('filters.type', 'blacklist')
        );
    }

    public function test_admin_can_add_blacklist_entry(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/fraud-directory', [
            'type' => 'blacklist',
            'phone_number' => '+8801799887766',
            'ip_address' => '103.145.12.99',
            'customer_name' => 'Fraud Ring Actor',
            'reason' => 'High cancellation courier fraud ring',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_fraud_lists', [
            'type' => 'blacklist',
            'phone_number' => '01799887766', // Normalized to local 11-digit
            'ip_address' => '103.145.12.99',
            'customer_name' => 'Fraud Ring Actor',
            'blocked_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_add_whitelist_entry(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/fraud-directory', [
            'type' => 'whitelist',
            'phone_number' => '01811223344',
            'customer_name' => 'VIP Regular Customer',
            'reason' => 'Verified corporate partner',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_fraud_lists', [
            'type' => 'whitelist',
            'phone_number' => '01811223344',
            'customer_name' => 'VIP Regular Customer',
        ]);
    }

    public function test_adding_entry_requires_at_least_one_identifier(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/fraud-directory', [
            'type' => 'blacklist',
            'customer_name' => 'Nameless Without Identifier',
            'reason' => 'Missing ID info',
        ]);

        $response->assertSessionHasErrors(['identifier']);
    }

    public function test_admin_can_delete_fraud_entry(): void
    {
        $entry = CustomerFraudList::create([
            'type' => CustomerFraudList::TYPE_BLACKLIST,
            'phone_number' => '01700112233',
            'reason' => 'Temporary block',
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/fraud-directory/{$entry->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('customer_fraud_lists', [
            'id' => $entry->id,
        ]);
    }

    public function test_check_customer_fraud_list_action_matches_normalized_phone_and_ip(): void
    {
        CustomerFraudList::create([
            'type' => CustomerFraudList::TYPE_BLACKLIST,
            'phone_number' => '01712398765',
            'ip_address' => '103.50.40.30',
            'reason' => 'Banned by risk team',
        ]);

        $checkAction = app(CheckCustomerFraudListAction::class);

        // 1. Matches via E.164 phone
        $resByPhone = $checkAction(phone: '+8801712398765');
        $this->assertTrue($resByPhone->isBlacklisted);
        $this->assertFalse($resByPhone->isWhitelisted);

        // 2. Matches via IP address
        $resByIp = $checkAction(ipAddress: '103.50.40.30');
        $this->assertTrue($resByIp->isBlacklisted);

        // 3. Clean identifier returns false
        $resClean = $checkAction(phone: '01899887766');
        $this->assertFalse($resClean->isBlacklisted);
        $this->assertFalse($resClean->isWhitelisted);
    }

    public function test_blacklisted_customer_is_instantly_blocked_by_fraud_scoring_engine(): void
    {
        CustomerFraudList::create([
            'type' => CustomerFraudList::TYPE_BLACKLIST,
            'phone_number' => '01712398765',
            'reason' => 'Repeat fake orders',
        ]);

        $calculateFraudScore = app(CalculateOrderFraudRiskScoreAction::class);

        $assessment = $calculateFraudScore([
            'customer_name' => 'Valid Name',
            'customer_phone' => '01712398765',
            'delivery_address' => 'House 1, Road 2, Banani, Dhaka',
            'skip_courier_lookup' => true,
        ]);

        $this->assertEquals(100, $assessment->score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_BLACKLISTED, $assessment->riskLevel);
        $this->assertFalse($assessment->isCodAllowed);
        $this->assertStringContainsString('blacklist', $assessment->reasons[0]);
    }

    public function test_whitelisted_customer_bypasses_all_fraud_friction(): void
    {
        CustomerFraudList::create([
            'type' => CustomerFraudList::TYPE_WHITELIST,
            'phone_number' => '01712398765',
            'reason' => 'VIP partner',
        ]);

        $calculateFraudScore = app(CalculateOrderFraudRiskScoreAction::class);

        $assessment = $calculateFraudScore([
            'customer_name' => 'asdfghjkl', // Gibberish name that would normally penalize!
            'customer_phone' => '01712398765',
            'delivery_address' => 'Banani, Dhaka',
            'skip_courier_lookup' => true,
        ]);

        // Whitelisted customer gets score 0 and low risk!
        $this->assertEquals(0, $assessment->score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_LOW, $assessment->riskLevel);
        $this->assertTrue($assessment->isCodAllowed);
        $this->assertFalse($assessment->requiresOtp);
        $this->assertEmpty($assessment->reasons);
    }
}
