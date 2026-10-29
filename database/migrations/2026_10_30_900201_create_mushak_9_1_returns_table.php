<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_9_1_returns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('tax_period', 7)->index(); // YYYY-MM
            $table->string('return_type', 16)->default('original'); // original, revised, complete
            $table->string('status', 20)->default('draft'); // draft, finalized, submitted

            // Taxpayer particulars
            $table->string('taxpayer_name');
            $table->string('taxpayer_bin', 32)->index();
            $table->text('taxpayer_address');
            $table->string('economic_activity');
            $table->string('tax_circle')->nullable();
            $table->string('tax_commissionerate')->nullable();

            // Part 3: Outward Supplies & Output Tax (বিক্রয় ও প্রদেয় কর)
            $table->decimal('sales_standard_rated_value_bdt', 14, 4)->default(0);
            $table->decimal('sales_standard_rated_vat_bdt', 14, 4)->default(0);
            $table->decimal('sales_retail_trade_value_bdt', 14, 4)->default(0);
            $table->decimal('sales_retail_trade_vat_bdt', 14, 4)->default(0);
            $table->decimal('sales_exempt_value_bdt', 14, 4)->default(0);
            $table->decimal('total_output_vat_bdt', 14, 4)->default(0);

            // Part 4: Inward Supplies & Rebatable Input Tax Credit (ক্রয় ও রেয়াত)
            $table->decimal('local_purchase_value_bdt', 14, 4)->default(0);
            $table->decimal('local_purchase_vat_bdt', 14, 4)->default(0);
            $table->decimal('import_assessment_value_bdt', 14, 4)->default(0);
            $table->decimal('import_vat_rebatable_bdt', 14, 4)->default(0);
            $table->decimal('import_advance_tax_at_bdt', 14, 4)->default(0);
            $table->decimal('total_input_tax_credit_bdt', 14, 4)->default(0);

            // Part 5: Increasing & Decreasing Adjustments (সমন্বয়সমূহ)
            $table->decimal('decreasing_adjustments_returns_bdt', 14, 4)->default(0);
            $table->decimal('decreasing_adjustments_other_bdt', 14, 4)->default(0);
            $table->decimal('total_decreasing_adjustments_bdt', 14, 4)->default(0);
            $table->decimal('increasing_adjustments_vds_bdt', 14, 4)->default(0);
            $table->decimal('increasing_adjustments_returns_bdt', 14, 4)->default(0);
            $table->decimal('total_increasing_adjustments_bdt', 14, 4)->default(0);

            // Part 6: Net Tax Payable / Refundable Carry-Forward (নিট হিসাব)
            $table->decimal('net_vat_payable_bdt', 14, 4)->default(0);
            $table->decimal('closing_balance_credit_bdt', 14, 4)->default(0);

            // Submission & Treasury details
            $table->string('submission_reference', 64)->nullable()->index();
            $table->dateTime('submitted_at')->nullable();
            $table->string('treasury_challan_number', 64)->nullable();
            $table->date('treasury_challan_date')->nullable();
            $table->decimal('treasury_deposit_amount_bdt', 14, 4)->default(0);

            $table->json('summary_metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tax_period', 'return_type'], 'm91_period_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_9_1_returns');
    }
};
