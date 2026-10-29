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
        Schema::create('boe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_entry_id')->constrained('bills_of_entry')->cascadeOnDelete();
            $table->foreignId('import_po_item_id')->nullable()->constrained('import_po_items')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('hs_code', 20)->index();
            $table->string('item_description');
            $table->unsignedInteger('quantity');
            $table->decimal('customs_assessment_value_bdt', 14, 4);
            $table->decimal('cd_rate_percent', 5, 2)->default(0.00);
            $table->decimal('cd_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('rd_rate_percent', 5, 2)->default(0.00);
            $table->decimal('rd_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('sd_rate_percent', 5, 2)->default(0.00);
            $table->decimal('sd_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
            $table->decimal('vat_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('at_rate_percent', 5, 2)->default(5.00); // 5% Advance Tax
            $table->decimal('at_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('ait_rate_percent', 5, 2)->default(5.00); // 5% Advance Income Tax
            $table->decimal('ait_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('total_duty_bdt', 14, 4)->default(0.0000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boe_items');
    }
};
