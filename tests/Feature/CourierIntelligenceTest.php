<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Logistics\DTOs\CourierDeliveryProfileDto;
use App\Modules\Logistics\Services\AggregatedCourierIntelligenceService;
use App\Modules\Logistics\Services\PathaoDeliveryIntelligenceService;
use App\Modules\Logistics\Services\SteadfastDeliveryIntelligenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CourierIntelligenceTest extends TestCase
{
    private string $testPhone = '01712398765';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_steadfast_delivery_intelligence_parses_successful_response(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'status' => 200,
                'delivery_status' => [
                    'total_parcels' => 20,
                    'delivered' => 18,
                    'cancelled' => 2,
                ],
            ], 200),
        ]);

        $service = new SteadfastDeliveryIntelligenceService(
            baseUrl: 'https://ssl.steadfast.com.bd',
            apiKey: 'test_api_key',
            secretKey: 'test_secret_key'
        );

        $profile = $service->checkCustomerDeliveryHistory($this->testPhone);

        $this->assertEquals('steadfast', $profile->courier);
        $this->assertEquals($this->testPhone, $profile->phone);
        $this->assertEquals(20, $profile->totalParcels);
        $this->assertEquals(18, $profile->deliveredCount);
        $this->assertEquals(2, $profile->cancelledCount);
        $this->assertEquals(90.0, $profile->successRatePercent);
        $this->assertEquals(10.0, $profile->returnRatePercent);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_LOW, $profile->riskTier);
        $this->assertFalse($profile->isHighRisk);
    }

    public function test_steadfast_delivery_intelligence_flags_high_return_rate(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'status' => 200,
                'delivery_status' => [
                    'total_parcels' => 10,
                    'delivered' => 4,
                    'cancelled' => 6,
                ],
            ], 200),
        ]);

        $service = new SteadfastDeliveryIntelligenceService(
            baseUrl: 'https://ssl.steadfast.com.bd',
            apiKey: 'test_api_key',
            secretKey: 'test_secret_key'
        );

        $profile = $service->checkCustomerDeliveryHistory('+8801712398765');

        $this->assertEquals(10, $profile->totalParcels);
        $this->assertEquals(60.0, $profile->returnRatePercent);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_HIGH, $profile->riskTier);
        $this->assertTrue($profile->isHighRisk);
    }

    public function test_steadfast_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $service = new SteadfastDeliveryIntelligenceService(
            baseUrl: 'https://ssl.steadfast.com.bd',
            apiKey: 'test_api_key',
            secretKey: 'test_secret_key'
        );

        $profile = $service->checkCustomerDeliveryHistory($this->testPhone);

        $this->assertEquals('steadfast', $profile->courier);
        $this->assertEquals(0, $profile->totalParcels);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_UNKNOWN, $profile->riskTier);
        $this->assertFalse($profile->isHighRisk);
    }

    public function test_pathao_delivery_intelligence_parses_successful_response(): void
    {
        Http::fake([
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'type' => 'success',
                'data' => [
                    'total_orders' => 25,
                    'successful_orders' => 23,
                    'returned_orders' => 2,
                ],
            ], 200),
        ]);

        $service = new PathaoDeliveryIntelligenceService(
            baseUrl: 'https://courier.pathao.com',
            token: 'valid_bearer_token'
        );

        $profile = $service->checkCustomerDeliveryHistory($this->testPhone);

        $this->assertEquals('pathao', $profile->courier);
        $this->assertEquals(25, $profile->totalParcels);
        $this->assertEquals(23, $profile->deliveredCount);
        $this->assertEquals(2, $profile->cancelledCount);
        $this->assertEquals(92.0, $profile->successRatePercent);
        $this->assertEquals(8.0, $profile->returnRatePercent);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_LOW, $profile->riskTier);
        $this->assertFalse($profile->isHighRisk);
    }

    public function test_pathao_delivery_intelligence_flags_high_return_customer(): void
    {
        Http::fake([
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'type' => 'success',
                'data' => [
                    'total_orders' => 12,
                    'successful_orders' => 5,
                    'returned_orders' => 7,
                ],
            ], 200),
        ]);

        $service = new PathaoDeliveryIntelligenceService(
            baseUrl: 'https://courier.pathao.com',
            token: 'valid_bearer_token'
        );

        $profile = $service->checkCustomerDeliveryHistory($this->testPhone);

        $this->assertEquals(12, $profile->totalParcels);
        $this->assertEquals(58.33, $profile->returnRatePercent);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_HIGH, $profile->riskTier);
        $this->assertTrue($profile->isHighRisk);
    }

    public function test_pathao_handles_missing_token_or_network_error_gracefully(): void
    {
        // Service without token
        $serviceNoToken = new PathaoDeliveryIntelligenceService(
            baseUrl: 'https://courier.pathao.com',
            token: null
        );

        $profile = $serviceNoToken->checkCustomerDeliveryHistory($this->testPhone);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_UNKNOWN, $profile->riskTier);
        $this->assertEquals(0, $profile->totalParcels);

        // Service with network error
        Http::fake([
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response(null, 503),
        ]);

        $serviceWithError = new PathaoDeliveryIntelligenceService(
            baseUrl: 'https://courier.pathao.com',
            token: 'some_token'
        );

        $profileError = $serviceWithError->checkCustomerDeliveryHistory($this->testPhone);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_UNKNOWN, $profileError->riskTier);
    }

    public function test_aggregated_courier_intelligence_combines_cross_courier_data(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'delivery_status' => [
                    'total_parcels' => 10,
                    'delivered' => 8,
                    'cancelled' => 2,
                ],
            ], 200),
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'data' => [
                    'total_orders' => 20,
                    'successful_orders' => 17,
                    'returned_orders' => 3,
                ],
            ], 200),
        ]);

        $steadfast = new SteadfastDeliveryIntelligenceService(
            baseUrl: 'https://ssl.steadfast.com.bd',
            apiKey: 'key',
            secretKey: 'secret'
        );

        $pathao = new PathaoDeliveryIntelligenceService(
            baseUrl: 'https://courier.pathao.com',
            token: 'token'
        );

        $aggregator = new AggregatedCourierIntelligenceService($steadfast, $pathao);
        $profile = $aggregator->getAggregatedProfile($this->testPhone, skipCache: true);

        $this->assertEquals('aggregated', $profile->courier);
        $this->assertEquals(30, $profile->totalParcels);
        $this->assertEquals(25, $profile->deliveredCount);
        $this->assertEquals(5, $profile->cancelledCount);
        $this->assertEquals(83.33, $profile->successRatePercent);
        $this->assertEquals(16.67, $profile->returnRatePercent);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_LOW, $profile->riskTier);
        $this->assertFalse($profile->isHighRisk);
        $this->assertArrayHasKey('courier_breakdown', (array) $profile->rawPayload);
    }

    public function test_aggregated_courier_intelligence_flags_overall_high_risk(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'delivery_status' => [
                    'total_parcels' => 6,
                    'delivered' => 2,
                    'cancelled' => 4,
                ],
            ], 200),
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'data' => [
                    'total_orders' => 4,
                    'successful_orders' => 1,
                    'returned_orders' => 3,
                ],
            ], 200),
        ]);

        $steadfast = new SteadfastDeliveryIntelligenceService('https://ssl.steadfast.com.bd', 'k', 's');
        $pathao = new PathaoDeliveryIntelligenceService('https://courier.pathao.com', 'tok');

        $aggregator = new AggregatedCourierIntelligenceService($steadfast, $pathao);
        $profile = $aggregator->getAggregatedProfile($this->testPhone, skipCache: true);

        $this->assertEquals(10, $profile->totalParcels);
        $this->assertEquals(3, $profile->deliveredCount);
        $this->assertEquals(7, $profile->cancelledCount);
        $this->assertEquals(70.0, $profile->returnRatePercent);
        $this->assertEquals(CourierDeliveryProfileDto::TIER_HIGH, $profile->riskTier);
        $this->assertTrue($profile->isHighRisk);
    }

    public function test_aggregated_courier_intelligence_caches_profile_by_phone(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'delivery_status' => ['total_parcels' => 5, 'delivered' => 5, 'cancelled' => 0],
            ], 200),
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'data' => ['total_orders' => 5, 'successful_orders' => 5, 'returned_orders' => 0],
            ], 200),
        ]);

        $steadfast = new SteadfastDeliveryIntelligenceService('https://ssl.steadfast.com.bd', 'k', 's');
        $pathao = new PathaoDeliveryIntelligenceService('https://courier.pathao.com', 'tok');
        $aggregator = new AggregatedCourierIntelligenceService($steadfast, $pathao);

        // First request - populates cache
        $first = $aggregator->getAggregatedProfile($this->testPhone, skipCache: false);
        $this->assertEquals(10, $first->totalParcels);

        // Re-fake HTTP with different data to ensure the second call reads from cache
        Http::fake([
            '*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $cached = $aggregator->getAggregatedProfile($this->testPhone, skipCache: false);
        $this->assertEquals(10, $cached->totalParcels);
        $this->assertEquals(100.0, $cached->successRatePercent);
    }
}
