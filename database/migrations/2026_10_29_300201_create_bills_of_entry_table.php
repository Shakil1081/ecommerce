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
        Schema::create('bills_of_entry', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('import_purchase_order_id')->constrained('import_purchase_orders')->cascadeOnDelete();
            $table->string('boe_number')->unique();
            $table->date('boe_date');
            $table->string('customs_station')->default('chattogram_port')->index();
            $table->string('c_and_f_agent_name')->nullable();
            $table->string('c_and_f_license_number')->nullable();
            $table->decimal('customs_assessment_value_bdt', 14, 4)->default(0.0000);
            $table->decimal('customs_duty_cd', 14, 4)->default(0.0000);
            $table->decimal('regulatory_duty_rd', 14, 4)->default(0.0000);
            $table->decimal('supplementary_duty_sd', 14, 4)->default(0.0000);
            $table->decimal('vat_import', 14, 4)->default(0.0000);
            $table->decimal('advance_tax_at', 14, 4)->default(0.0000); // AT 5% (Mushak 9.1 Rebate)
            $table->decimal('advance_income_tax_ait', 14, 4)->default(0.0000); // AIT 5%
            $table->decimal('customs_fine_penalty', 14, 4)->default(0.0000);
            $table->decimal('total_customs_duties_bdt', 14, 4)->default(0.0000);
            $table->enum('payment_status', ['unpaid', 'paid', 'assessed'])->default('unpaid')->index();
            $table->date('customs_payment_date')->nullable();
            $table->string('customs_challan_number')->nullable()->index();
            $table->enum('assessment_status', [
                'pending',
                'assessed',
                'examination_passed',
                'out_of_charge',
            ])->default('pending')->index();
            $table->date('out_of_charge_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills_of_entry');
    }
};
