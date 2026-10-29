<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use Database\Seeders\BangladeshGeoLocationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BangladeshGeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BangladeshGeoLocationsSeeder::class);
    }

    public function test_all_eight_divisions_are_seeded(): void
    {
        $this->assertDatabaseCount('bangladesh_divisions', 8);

        $divisions = ['Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh'];
        foreach ($divisions as $name) {
            $this->assertDatabaseHas('bangladesh_divisions', ['name_en' => $name, 'is_active' => true]);
        }

        $dhaka = BangladeshDivision::where('name_en', 'Dhaka')->first();
        $this->assertNotNull($dhaka);
        $this->assertEquals('ঢাকা', $dhaka->name_bn);
    }

    public function test_districts_are_classified_into_proper_delivery_zones(): void
    {
        // 1. Inside Dhaka zone
        $insideDhaka = BangladeshDistrict::insideDhaka()->pluck('name_en')->toArray();
        $this->assertContains('Dhaka', $insideDhaka);

        // 2. Dhaka Suburbs zone
        $suburbs = BangladeshDistrict::dhakaSuburbs()->pluck('name_en')->toArray();
        $this->assertContains('Gazipur', $suburbs);
        $this->assertContains('Narayanganj', $suburbs);
        $this->assertContains('Munshiganj', $suburbs);
        $this->assertContains('Manikganj', $suburbs);
        $this->assertContains('Narsingdi', $suburbs);

        // 3. Outside Dhaka zone
        $outsideDhaka = BangladeshDistrict::outsideDhaka()->pluck('name_en')->toArray();
        $this->assertContains('Chattogram', $outsideDhaka);
        $this->assertContains('Sylhet', $outsideDhaka);
        $this->assertContains('Khulna', $outsideDhaka);
    }

    public function test_cascading_geographic_relationships(): void
    {
        $dhakaDiv = BangladeshDivision::where('name_en', 'Dhaka')->first();
        $this->assertNotNull($dhakaDiv);

        // Division has multiple districts
        $districts = $dhakaDiv->districts;
        $this->assertGreaterThan(5, $districts->count());

        // District has thanas
        $dhakaDistrict = $districts->firstWhere('name_en', 'Dhaka');
        $this->assertNotNull($dhakaDistrict);

        $thanas = $dhakaDistrict->thanas;
        $this->assertGreaterThan(10, $thanas->count());

        $dhanmondi = $thanas->firstWhere('name_en', 'Dhanmondi');
        $this->assertNotNull($dhanmondi);
        $this->assertEquals('ধানমন্ডি', $dhanmondi->name_bn);
        $this->assertEquals('1209', $dhanmondi->post_code);

        // BelongsTo relationship
        $this->assertEquals($dhakaDistrict->id, $dhanmondi->district->id);
    }
}
