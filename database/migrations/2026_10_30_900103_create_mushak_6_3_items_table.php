<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_6_3_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mushak_6_3_invoice_id')->constrained('mushak_6_3_invoices')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('item_name');
            $table->string('sku');
            $table->string('hs_code', 32)->nullable();
            $table->string('unit_of_measure', 16)->default('PCS');
            $table->unsignedInteger('quantity');

            // Unit & subtotal breakdown (excluding tax)
            $table->decimal('unit_price_exclusive_bdt', 14, 4);
            $table->decimal('subtotal_exclusive_bdt', 14, 4);

            // Supplementary Duty (SD)
            $table->decimal('sd_rate_percent', 6, 2)->default(0.00);
            $table->decimal('sd_amount_bdt', 14, 4)->default(0.00);

            // VAT breakdown
            $table->decimal('vat_rate_percent', 6, 2)->default(15.00);
            $table->decimal('vat_amount_bdt', 14, 4)->default(0.00);

            // Total inclusive of SD and VAT
            $table->decimal('total_with_tax_bdt', 14, 4);
            $table->boolean('is_vat_exempt')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_3_items');
    }
};
