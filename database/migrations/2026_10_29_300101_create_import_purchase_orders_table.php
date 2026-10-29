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
        Schema::create('import_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->enum('status', [
                'draft',
                'pi_received',
                'lc_opened',
                'in_transit',
                'customs_clearing',
                'received',
                'cancelled',
            ])->default('draft')->index();
            $table->string('proforma_invoice_number')->nullable()->index();
            $table->date('proforma_invoice_date')->nullable();
            $table->string('commercial_invoice_number')->nullable()->index();
            $table->date('commercial_invoice_date')->nullable();
            $table->enum('payment_method', [
                'letter_of_credit',
                'telegraphic_transfer',
                'open_account',
            ])->default('letter_of_credit');
            $table->string('lc_number')->nullable()->index();
            $table->string('lc_issuing_bank')->nullable();
            $table->date('lc_issue_date')->nullable();
            $table->decimal('lc_margin_percentage', 5, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate_to_bdt', 14, 4)->default(1.0000);
            $table->decimal('total_fob_foreign', 14, 4)->default(0.0000);
            $table->decimal('total_fob_bdt', 14, 4)->default(0.0000);
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->default('Chattogram');
            $table->string('bill_of_lading_number')->nullable()->index();
            $table->string('vessel_or_flight_name')->nullable();
            $table->date('estimated_arrival_date')->nullable();
            $table->date('actual_arrival_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_purchase_orders');
    }
};
