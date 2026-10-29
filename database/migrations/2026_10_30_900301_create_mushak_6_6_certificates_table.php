<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_6_6_certificates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('certificate_number', 32)->unique(); // M6.6-YYYYMM-XXXXXX
            $table->string('tax_period', 7)->index(); // YYYY-MM
            $table->dateTime('issue_date');

            // Withholding Entity (Our Company)
            $table->string('withholding_entity_name');
            $table->string('withholding_entity_bin', 32)->index();
            $table->text('withholding_entity_address');
            $table->string('tax_circle')->nullable();
            $table->string('tax_commissionerate')->nullable();

            // Supplier Withheld
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_bin', 32)->index();
            $table->text('supplier_address')->nullable();

            // Procurement / PO Link
            $table->foreignId('local_purchase_order_id')->nullable()->constrained('local_purchase_orders')->nullOnDelete();
            $table->string('supplier_invoice_number', 64); // Supplier Mushak 6.3
            $table->date('supplier_invoice_date');

            // Tax & VDS Breakdown
            $table->decimal('base_taxable_value_bdt', 14, 4);
            $table->decimal('vat_rate_percent', 6, 2)->default(15.00);
            $table->decimal('vat_amount_bdt', 14, 4);
            $table->decimal('vds_rate_percent', 6, 2);
            $table->decimal('vds_deducted_amount_bdt', 14, 4);
            $table->decimal('net_paid_to_supplier_bdt', 14, 4);

            // Treasury Deposit Details (উৎসে কর্তিত কর জমাদানের বিবরণ)
            $table->string('treasury_challan_number', 64)->nullable();
            $table->date('treasury_challan_date')->nullable();
            $table->string('treasury_bank_name')->nullable();
            $table->string('treasury_branch_name')->nullable();
            $table->string('treasury_account_code')->nullable();

            // Status & Integrity
            $table->string('status', 24)->default('issued'); // issued, deposited, cancelled
            $table->string('integrity_hash', 64);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_6_certificates');
    }
};
