<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_6_3_invoices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('invoice_number', 32)->unique();
            $table->string('tax_period', 7)->index(); // YYYY-MM
            $table->dateTime('issue_date');

            // Seller statutory information
            $table->string('seller_name');
            $table->string('seller_bin', 32)->index();
            $table->text('seller_address');
            $table->string('tax_circle')->nullable();
            $table->string('tax_commissionerate')->nullable();

            // Customer / Purchaser statutory information
            $table->string('customer_name');
            $table->string('customer_bin', 32)->nullable()->index();
            $table->string('customer_phone', 32);
            $table->text('customer_address');
            $table->text('destination_address');
            $table->string('vehicle_type_and_number')->nullable();

            // Financial & VAT breakdowns
            $table->decimal('subtotal_exclusive_bdt', 14, 4);
            $table->decimal('supplementary_duty_total_bdt', 14, 4)->default(0);
            $table->decimal('vat_total_bdt', 14, 4);
            $table->decimal('shipping_fee_bdt', 14, 4)->default(0);
            $table->decimal('discount_amount_bdt', 14, 4)->default(0);
            $table->decimal('grand_total_bdt', 14, 4);
            $table->boolean('is_exempt')->default(false);

            // Audit, integrity & lifecycle
            $table->string('status', 24)->default('issued'); // issued, adjusted, cancelled
            $table->string('integrity_hash', 64);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_3_invoices');
    }
};
