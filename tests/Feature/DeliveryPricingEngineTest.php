<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Actions\CalculateShippingRateAction;
use App\Modules\Logistics\Models\ShippingRate;
use App\Modules\Logistics\Models\ShippingZone;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private BangladeshDivision $dhakaDivision;

    private BangladeshDistrict $dhakaCity;

    private BangladeshDistrict $savarSuburbs;

    private BangladeshDistrict $chittagong;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingZoneSeeder::class);

        $this->dhakaDivision = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $ctgDivision = BangladeshDivision::create(['name_en' => 'Chattogram', 'name_bn' => 'চট্টগ্রাম']);

        $this->dhakaCity = BangladeshDistrict::create([
            'division_id' => $this->dhakaDivision->id,
            'name_en' => 'Dhaka City',
            'name_bn' => 'ঢাকা সিটি',
            'zone_type' => BangladeshDistrict::ZONE_INSIDE_DHAKA,
        ]);

        $this->savarSuburbs = BangladeshDistrict::create([
            'division_id' => $this->dhakaDivision->id,
            'name_en' => 'Gazipur',
            'name_bn' => 'গাজীপুর',
            'zone_type' => BangladeshDistrict::ZONE_DHAKA_SUBURBS,
        ]);

        $this->chittagong = BangladeshDistrict::create([
            'division_id' => $ctgDivision->id,
            'name_en' => 'Chattogram',
            'name_bn' => 'চট্টগ্রাম',
            'zone_type' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA,
        ]);
    }

    public function test_shipping_zone_and_rate_models_persist_correctly(): void
    {
        $zone = ShippingZone::where('code', ShippingZone::CODE_INSIDE_DHAKA)->first();
        $this->assertNotNull($zone);
        $this->assertSame('Inside Dhaka (ঢাকা সিটি)', $zone->name);
        $this->assertCount(1, $zone->rates);

        $rate = $zone->rates->first();
        $this->assertSame('70.0000', (string) $rate->base_rate_bdt);
        $this->assertSame('20.0000', (string) $rate->extra_rate_per_kg_bdt);
        $this->assertSame('2500.0000', (string) $rate->free_shipping_threshold_bdt);
    }

    public function test_calculate_rate_inside_dhaka_base_weight(): void
    {
        $action = app(CalculateShippingRateAction::class);
        $result = $action(
            district: $this->dhakaCity,
            weightKg: 0.8,
            cartSubtotalBdt: 1200.0,
        );

        $this->assertSame(ShippingZone::CODE_INSIDE_DHAKA, $result->zoneCode);
        $this->assertSame('70.0000', $result->baseRateBdt);
        $this->assertSame('0.0000', $result->extraRateBdt);
        $this->assertSame('70.0000', $result->totalShippingFeeBdt);
        $this->assertFalse($result->isFreeShipping);
        $this->assertSame(1, $result->estimatedDaysMin);
        $this->assertSame(2, $result->estimatedDaysMax);
    }

    public function test_calculate_rate_inside_dhaka_with_extra_weight(): void
    {
        $action = app(CalculateShippingRateAction::class);
        // Base weight is 1.0kg. 3.2kg means 2.2kg extra -> ceil(2.2) = 3 extra kg * ৳20 = +৳60
        $result = $action(
            district: $this->dhakaCity,
            weightKg: 3.2,
            cartSubtotalBdt: 1500.0,
        );

        $this->assertSame('70.0000', $result->baseRateBdt);
        $this->assertSame('60.0000', $result->extraRateBdt);
        $this->assertSame('130.0000', $result->totalShippingFeeBdt);
        $this->assertFalse($result->isFreeShipping);
    }

    public function test_calculate_rate_dhaka_suburbs(): void
    {
        $action = app(CalculateShippingRateAction::class);
        $result = $action(
            district: $this->savarSuburbs,
            weightKg: 1.0,
            cartSubtotalBdt: 1800.0,
        );

        $this->assertSame(ShippingZone::CODE_DHAKA_SUBURBS, $result->zoneCode);
        $this->assertSame('100.0000', $result->baseRateBdt);
        $this->assertSame('100.0000', $result->totalShippingFeeBdt);
        $this->assertFalse($result->isFreeShipping);
    }

    public function test_calculate_rate_outside_dhaka(): void
    {
        $action = app(CalculateShippingRateAction::class);
        $result = $action(
            district: $this->chittagong,
            weightKg: 1.0,
            cartSubtotalBdt: 2000.0,
        );

        $this->assertSame(ShippingZone::CODE_OUTSIDE_DHAKA, $result->zoneCode);
        $this->assertSame('130.0000', $result->baseRateBdt);
        $this->assertSame('130.0000', $result->totalShippingFeeBdt);
        $this->assertSame(3, $result->estimatedDaysMin);
        $this->assertSame(5, $result->estimatedDaysMax);
    }

    public function test_calculate_rate_free_shipping_threshold_exceeded(): void
    {
        $action = app(CalculateShippingRateAction::class);
        // Inside Dhaka free shipping threshold is 2500
        $result = $action(
            district: $this->dhakaCity,
            weightKg: 4.5,
            cartSubtotalBdt: 2750.0,
        );

        $this->assertTrue($result->isFreeShipping);
        $this->assertSame('0.0000', $result->totalShippingFeeBdt);
    }

    public function test_calculate_rate_courier_specific_override(): void
    {
        $zone = ShippingZone::where('code', ShippingZone::CODE_INSIDE_DHAKA)->first();
        $this->assertNotNull($zone);

        // Add a promotional Steadfast courier rate (৳55 base instead of standard ৳70)
        ShippingRate::create([
            'zone_id' => $zone->id,
            'courier_code' => 'steadfast',
            'base_weight_kg' => '1.000',
            'base_rate_bdt' => '55.0000',
            'extra_rate_per_kg_bdt' => '15.0000',
            'free_shipping_threshold_bdt' => '2000.0000',
            'is_active' => true,
        ]);

        $action = app(CalculateShippingRateAction::class);

        // Standard rate without courier code specified
        $standard = $action(district: $this->dhakaCity, weightKg: 1.0, cartSubtotalBdt: 1000.0);
        $this->assertSame('70.0000', $standard->totalShippingFeeBdt);

        // Steadfast override applied
        $steadfast = $action(
            district: $this->dhakaCity,
            weightKg: 1.0,
            cartSubtotalBdt: 1000.0,
            courierCode: 'steadfast'
        );
        $this->assertSame('55.0000', $steadfast->totalShippingFeeBdt);
        $this->assertSame('steadfast', $steadfast->courierCode);
    }

    public function test_shipping_zone_seeder_initializes_all_three_bangladesh_zones(): void
    {
        $this->assertDatabaseHas('shipping_zones', ['code' => 'inside_dhaka']);
        $this->assertDatabaseHas('shipping_zones', ['code' => 'dhaka_suburbs']);
        $this->assertDatabaseHas('shipping_zones', ['code' => 'outside_dhaka']);

        $this->assertSame(3, ShippingZone::count());
        $this->assertSame(3, ShippingRate::whereNull('courier_code')->count());
    }
}
