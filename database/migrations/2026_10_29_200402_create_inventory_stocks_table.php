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
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('physical_quantity')->default(0); // On-hand count
            $table->integer('reserved_quantity')->default(0); // Committed to unfulfilled orders
            $table->string('bin_location', 60)->nullable(); // e.g. AISLE-2-SHELF-B4
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_variant_id']);
            $table->index('bin_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};

