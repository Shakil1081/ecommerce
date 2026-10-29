<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Logistics\Models\ShippingRate;
use App\Modules\Logistics\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'code' => ShippingZone::CODE_INSIDE_DHAKA,
                'name' => 'Inside Dhaka (ঢাকা সিটি)',
                'description' => 'Dhaka Metropolitan City delivery within 24-48 hours',
                'sort_order' => 1,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'rate' => [
                    'base_weight_kg' => '1.000',
                    'base_rate_bdt' => '70.0000',
                    'extra_rate_per_kg_bdt' => '20.0000',
                    'free_shipping_threshold_bdt' => '2500.0000',
                ],
            ],
            [
                'code' => ShippingZone::CODE_DHAKA_SUBURBS,
                'name' => 'Dhaka Suburbs (ঢাকা উপশহর)',
                'description' => 'Savar, Gazipur, Keraniganj, Narayanganj within 48-72 hours',
                'sort_order' => 2,
                'estimated_days_min' => 2,
                'estimated_days_max' => 3,
                'rate' => [
                    'base_weight_kg' => '1.000',
                    'base_rate_bdt' => '100.0000',
                    'extra_rate_per_kg_bdt' => '25.0000',
                    'free_shipping_threshold_bdt' => '3000.0000',
                ],
            ],
            [
                'code' => ShippingZone::CODE_OUTSIDE_DHAKA,
                'name' => 'Outside Dhaka (ঢাকার বাইরে সারা বাংলাদেশ)',
                'description' => 'Nationwide delivery across all 64 districts within 3-5 days',
                'sort_order' => 3,
                'estimated_days_min' => 3,
                'estimated_days_max' => 5,
                'rate' => [
                    'base_weight_kg' => '1.000',
                    'base_rate_bdt' => '130.0000',
                    'extra_rate_per_kg_bdt' => '30.0000',
                    'free_shipping_threshold_bdt' => '3500.0000',
                ],
            ],
        ];

        foreach ($zones as $zoneData) {
            $rateData = $zoneData['rate'];
            unset($zoneData['rate']);

            $zone = ShippingZone::updateOrCreate(
                ['code' => $zoneData['code']],
                $zoneData
            );

            ShippingRate::updateOrCreate(
                [
                    'zone_id' => $zone->id,
                    'courier_code' => null,
                ],
                [
                    'base_weight_kg' => $rateData['base_weight_kg'],
                    'base_rate_bdt' => $rateData['base_rate_bdt'],
                    'extra_rate_per_kg_bdt' => $rateData['extra_rate_per_kg_bdt'],
                    'free_shipping_threshold_bdt' => $rateData['free_shipping_threshold_bdt'],
                    'is_active' => true,
                ]
            );
        }
    }
}
