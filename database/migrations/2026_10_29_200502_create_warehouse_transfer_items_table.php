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
        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity'); // Transferred quantity (must be > 0)
            $table->timestamps();

            $table->unique(['transfer_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
