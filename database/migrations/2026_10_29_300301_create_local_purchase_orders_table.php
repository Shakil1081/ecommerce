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
        Schema::create('local_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('order_date');
            $table->date('delivery_deadline')->nullable();
            $table->enum('status', [
                'draft',
                'issued',
                'partially_received',
                'received',
                'cancelled',
            ])->default('draft')->index();
            $table->string('mushak_6_3_invoice_number')->nullable()->index();
            $table->date('mushak_6_3_invoice_date')->nullable();
            $table->decimal('subtotal_bdt', 14, 4)->default(0.0000);
            $table->decimal('vat_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('vds_rate_percent', 5, 2)->default(0.00);
            $table->decimal('vds_amount_bdt', 14, 4)->default(0.0000);
            $table->string('mushak_6_6_certificate_number')->nullable()->unique();
            $table->date('mushak_6_6_issue_date')->nullable();
            $table->decimal('tds_rate_percent', 5, 2)->default(0.00);
            $table->decimal('tds_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('total_payable_to_supplier_bdt', 14, 4)->default(0.0000);
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_purchase_orders');
    }
};
