<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('customer_group', 50)->default('wholesale_b2b'); // retail, wholesale_b2b, corporate_vip
            $table->integer('min_quantity')->default(1); // Volume break threshold / MOQ
            $table->decimal('tier_price_bdt', 14, 4); // Special tier price in BDT
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_variant_id', 'customer_group', 'min_quantity'], 'variant_group_qty_unique');
            $table->index(['customer_group', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_tiers');
    }
};

