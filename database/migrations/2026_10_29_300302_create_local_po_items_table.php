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
        Schema::create('local_po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_purchase_order_id')->constrained('local_purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('unit_price_bdt', 14, 4);
            $table->decimal('subtotal_bdt', 14, 4);
            $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
            $table->decimal('vat_amount_bdt', 14, 4);
            $table->decimal('total_item_cost_bdt', 14, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_po_items');
    }
};
