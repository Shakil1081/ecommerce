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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products');
            $table->foreignUlid('product_variant_id')->constrained('product_variants');
            $table->string('product_name', 255);
            $table->string('sku', 100);
            $table->json('attributes')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_bdt', 14, 4);
            $table->decimal('tier_savings_bdt', 14, 4)->default(0.0000);
            $table->decimal('subtotal_bdt', 14, 4);
            $table->decimal('unit_cogs_bdt', 14, 4)->default(0.0000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
