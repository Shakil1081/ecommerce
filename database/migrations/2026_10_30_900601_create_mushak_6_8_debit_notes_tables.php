<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_6_8_debit_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('debit_note_number', 32)->unique(); // M6.8-YYYYMM-XXXXXX
            $table->string('tax_period', 7)->index();          // YYYY-MM
            $table->dateTime('issue_date');

            // Supplier / Vendor details
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_name');
            $table->string('supplier_bin', 32)->index();
            $table->text('supplier_address')->nullable();

            // Buyer (Our Company)
            $table->string('buyer_name');
            $table->string('buyer_bin', 32)->index();
            $table->text('buyer_address');

            // Source Purchase reference
            $table->foreignId('local_purchase_order_id')->nullable()->constrained('local_purchase_orders')->nullOnDelete();
            $table->string('original_invoice_number', 64);
            $table->date('original_invoice_date');
            $table->text('reason_for_return');

            // Financial Breakdowns
            $table->decimal('returned_value_exclusive_bdt', 14, 4);
            $table->decimal('returned_sd_amount_bdt', 14, 4)->default(0);
            $table->decimal('returned_vat_amount_bdt', 14, 4); // Increasing Tax Adjustment (বৃদ্ধিকারী সমন্বয়)
            $table->decimal('total_debit_amount_bdt', 14, 4);

            $table->boolean('is_adjusted_in_mushak_9_1')->default(false);
            $table->string('status', 24)->default('issued'); // issued, settled, voided
            $table->string('integrity_hash', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mushak_6_8_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mushak_6_8_debit_note_id')->constrained('mushak_6_8_debit_notes')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('item_name');
            $table->string('sku');
            $table->unsignedInteger('quantity_returned');
            $table->decimal('unit_price_exclusive_bdt', 14, 4);
            $table->decimal('subtotal_exclusive_bdt', 14, 4);
            $table->decimal('vat_rate_percent', 6, 2)->default(15);
            $table->decimal('vat_amount_bdt', 14, 4);
            $table->decimal('total_debit_bdt', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_8_items');
        Schema::dropIfExists('mushak_6_8_debit_notes');
    }
};
