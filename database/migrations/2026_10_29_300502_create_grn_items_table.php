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
        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained('goods_received_notes')->cascadeOnDelete();
            $table->foreignId('import_po_item_id')->nullable()->constrained('import_po_items')->nullOnDelete();
            $table->foreignId('local_po_item_id')->nullable()->constrained('local_po_items')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->string('barcode_scanned', 64)->nullable()->index();
            $table->string('bin_location', 60)->nullable();
            $table->string('discrepancy_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grn_items');
    }
};
