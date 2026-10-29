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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode_ean', 64)->nullable()->index(); // Barcode/EAN-13 for warehouse scanner
            $table->json('attributes'); // Matrix options, e.g. {"Color": "Black", "Storage": "128GB"}
            $table->decimal('purchase_cost_bdt', 14, 4)->default(0); // Cost of Goods Sold / Landed Cost
            $table->decimal('selling_price_bdt', 14, 4); // Retail selling price
            $table->decimal('compare_at_price_bdt', 14, 4)->nullable(); // Strikethrough MSRP price
            $table->decimal('weight_kg', 8, 3)->default(0.500); // Dead weight for courier fee calculation
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

