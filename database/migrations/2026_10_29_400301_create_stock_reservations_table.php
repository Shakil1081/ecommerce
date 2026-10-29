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
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('status', ['reserved', 'committed', 'released'])->default('reserved')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['product_variant_id', 'warehouse_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
