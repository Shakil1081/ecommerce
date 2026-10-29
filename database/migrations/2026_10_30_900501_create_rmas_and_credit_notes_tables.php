<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Return Merchandise Authorizations (RMA)
        Schema::create('return_merchandise_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('rma_number', 32)->unique(); // RMA-YYYYMM-XXXXXX
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 24)->default('requested')->index(); // requested, approved, received, inspected, completed, rejected, cancelled
            $table->string('reason_category', 64); // defective, wrong_item, changed_mind, damaged_in_shipping
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->string('inspection_outcome', 32)->nullable(); // restock_available, quarantine_damaged, scrap
            $table->decimal('refund_amount_bdt', 14, 4)->default(0);
            $table->string('refund_status', 24)->default('pending'); // pending, processed, rejected
            $table->string('refund_method', 32)->nullable(); // original_gateway, bkash, nagad, bank_transfer, wallet_credit
            $table->string('refund_transaction_reference', 64)->nullable();

            $table->dateTime('approved_at')->nullable();
            $table->dateTime('inspected_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. RMA Items
        Schema::create('rma_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rma_id')->constrained('return_merchandise_authorizations')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('item_name');
            $table->string('sku');
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->decimal('unit_price_bdt', 14, 4);
            $table->decimal('line_refund_bdt', 14, 4)->default(0);
            $table->string('condition_note')->nullable();
            $table->timestamps();
        });

        // 3. Statutory Mushak 6.7 Credit Notes (বিক্রয় ফেরত চালানপত্র - হ্রাসকারী সমন্বয় দলিল)
        Schema::create('mushak_6_7_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('credit_note_number', 32)->unique(); // M6.7-YYYYMM-XXXXXX
            $table->string('tax_period', 7)->index();           // YYYY-MM
            $table->dateTime('issue_date');

            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('mushak_6_3_invoice_id')->constrained('mushak_6_3_invoices');
            $table->string('mushak_6_3_invoice_number', 32)->index();
            $table->dateTime('mushak_6_3_invoice_date');
            $table->foreignId('rma_id')->nullable()->constrained('return_merchandise_authorizations')->nullOnDelete();

            // Seller Particulars
            $table->string('seller_name');
            $table->string('seller_bin', 32)->index();
            $table->text('seller_address');

            // Customer Particulars
            $table->string('customer_name');
            $table->string('customer_bin', 32)->nullable()->index();
            $table->string('customer_phone', 32);
            $table->text('customer_address');
            $table->text('reason_for_return');

            // Financial Breakdowns
            $table->decimal('original_invoice_total_bdt', 14, 4);
            $table->decimal('returned_value_exclusive_bdt', 14, 4);
            $table->decimal('returned_sd_amount_bdt', 14, 4)->default(0);
            $table->decimal('returned_vat_amount_bdt', 14, 4); // Statutory Decreasing Tax Adjustment (হ্রাসকারী সমন্বয়)
            $table->decimal('total_credit_amount_bdt', 14, 4);

            // NBR 9.1 VAT Ledger Link
            $table->boolean('is_adjusted_in_mushak_9_1')->default(false);
            $table->foreignId('mushak_9_1_return_id')->nullable()->constrained('mushak_9_1_returns')->nullOnDelete();

            $table->string('status', 24)->default('issued'); // issued, refunded, voided
            $table->string('integrity_hash', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Mushak 6.7 Credit Note Line Items
        Schema::create('mushak_6_7_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mushak_6_7_credit_note_id')->constrained('mushak_6_7_credit_notes')->cascadeOnDelete();
            $table->foreignId('mushak_6_3_item_id')->nullable()->constrained('mushak_6_3_items')->nullOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('item_name');
            $table->string('sku');
            $table->unsignedInteger('quantity_returned');
            $table->decimal('unit_price_exclusive_bdt', 14, 4);
            $table->decimal('subtotal_exclusive_bdt', 14, 4);
            $table->decimal('sd_rate_percent', 6, 2)->default(0);
            $table->decimal('sd_amount_bdt', 14, 4)->default(0);
            $table->decimal('vat_rate_percent', 6, 2)->default(15);
            $table->decimal('vat_amount_bdt', 14, 4);
            $table->decimal('total_credit_bdt', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_7_items');
        Schema::dropIfExists('mushak_6_7_credit_notes');
        Schema::dropIfExists('rma_items');
        Schema::dropIfExists('return_merchandise_authorizations');
    }
};
