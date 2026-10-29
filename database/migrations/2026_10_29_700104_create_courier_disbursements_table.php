<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_disbursements', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('courier_code', 50)->index();
            $table->string('reference_invoice', 100)->nullable()->index();
            $table->date('disbursement_date')->index();
            $table->decimal('total_collected_bdt', 14, 4)->default(0.0000);
            $table->decimal('total_courier_fee_bdt', 14, 4)->default(0.0000);
            $table->decimal('net_disbursed_bdt', 14, 4)->default(0.0000);
            $table->string('status', 50)->default('pending')->index(); // pending, reconciled, discrepancy
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('courier_disbursement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disbursement_id')->constrained('courier_disbursements')->cascadeOnDelete();
            $table->string('consignment_id', 100)->index();
            $table->string('order_number', 100)->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('collected_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('expected_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('courier_fee_bdt', 14, 4)->default(0.0000);
            $table->decimal('discrepancy_amount_bdt', 14, 4)->default(0.0000);
            $table->boolean('is_reconciled')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_disbursement_items');
        Schema::dropIfExists('courier_disbursements');
    }
};
