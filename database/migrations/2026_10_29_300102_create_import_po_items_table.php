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
        Schema::create('import_po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_purchase_order_id')->constrained('import_purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('hs_code', 20)->nullable()->index();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('unit_price_foreign', 14, 4);
            $table->decimal('unit_price_bdt', 14, 4);
            $table->decimal('total_fob_foreign', 14, 4);
            $table->decimal('total_fob_bdt', 14, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_po_items');
    }
};
