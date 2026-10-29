<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_6_5_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mushak_6_5_challan_id')->constrained('mushak_6_5_challans')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('item_name');
            $table->string('sku');
            $table->string('hs_code', 32)->nullable();
            $table->string('unit_of_measure', 16)->default('PCS');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost_bdt', 14, 4);
            $table->decimal('total_valuation_bdt', 14, 4);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_5_items');
    }
};
