<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique(); // inside_dhaka, dhaka_suburbs, outside_dhaka
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedSmallInteger('estimated_days_min')->default(1);
            $table->unsignedSmallInteger('estimated_days_max')->default(3);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('courier_code', 50)->nullable()->index(); // null = standard/default rate
            $table->decimal('base_weight_kg', 8, 3)->default(1.000);
            $table->decimal('base_rate_bdt', 14, 4)->default(70.0000);
            $table->decimal('extra_rate_per_kg_bdt', 14, 4)->default(20.0000);
            $table->decimal('free_shipping_threshold_bdt', 14, 4)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
