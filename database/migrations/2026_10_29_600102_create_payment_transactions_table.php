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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway_code', 32)->index();
            $table->string('transaction_id', 100)->nullable()->index();
            $table->string('payment_purpose', 50)->default('order_full')->index(); // order_full, cod_advance_delivery_charge, partial_payment
            $table->decimal('amount_bdt', 14, 4);
            $table->decimal('gateway_fee_bdt', 14, 4)->default(0.0000);
            $table->string('currency', 10)->default('BDT');
            $table->enum('status', [
                'pending',
                'successful',
                'failed',
                'refunded',
                'cancelled',
            ])->default('pending')->index();
            $table->string('customer_account', 100)->nullable();
            $table->json('raw_request_payload')->nullable();
            $table->json('raw_response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
