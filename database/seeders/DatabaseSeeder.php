<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Roles & Granular Permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Create Default Super Admin Account
        $admin = User::firstOrCreate(
            ['email' => 'admin@ecommerce.test'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('Super Admin');
        }

        // 3. Seed Master Geo & Logistics & AI Configurations
        $this->call([
            BangladeshGeoLocationsSeeder::class,
            ShippingZoneSeeder::class,
            CourierGatewaySeeder::class,
            SmsGatewaySeeder::class,
            AiModelSettingSeeder::class,
        ]);
    }
}
