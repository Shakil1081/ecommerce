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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('order_number', 32)->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->index();

            // Order Lifecycle State Machine
            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'picked_and_packed',
                'handed_to_courier',
                'out_for_delivery',
                'delivered',
                'returned',
                'cancelled',
            ])->default('pending')->index();

            // Payment tracking
            $table->enum('payment_status', [
                'pending',
                'partial_advance',
                'paid',
                'refunded',
                'failed',
            ])->default('pending')->index();

            $table->enum('payment_method', [
                'cash_on_delivery',
                'bkash',
                'nagad',
                'sslcommerz',
                'bank_transfer',
            ])->default('cash_on_delivery')->index();

            // Customer & Delivery Address (Bangladesh Cascading Geography)
            $table->string('customer_name', 150);
            $table->string('customer_phone', 20)->index();
            $table->string('customer_email', 150)->nullable();
            $table->foreignId('division_id')->constrained('bangladesh_divisions');
            $table->foreignId('district_id')->constrained('bangladesh_districts');
            $table->foreignId('thana_id')->nullable()->constrained('bangladesh_thanas');
            $table->enum('delivery_zone', ['inside_dhaka', 'dhaka_suburbs', 'outside_dhaka'])->default('inside_dhaka')->index();
            $table->text('delivery_address');
            $table->text('customer_notes')->nullable();

            // Monetary Breakdown in BDT (DECIMAL 14, 4)
            $table->decimal('subtotal_bdt', 14, 4)->default(0.0000);
            $table->decimal('discount_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('shipping_fee_bdt', 14, 4)->default(0.0000);
            $table->decimal('total_bdt', 14, 4)->default(0.0000);
            $table->decimal('advance_paid_bdt', 14, 4)->default(0.0000);
            $table->decimal('payable_cod_amount_bdt', 14, 4)->default(0.0000);

            // Promotional & Coupon
            $table->string('coupon_code', 50)->nullable()->index();

            // Anti-Fraud & Risk Flagging
            $table->unsignedSmallInteger('fraud_risk_score')->default(0);
            $table->enum('fraud_risk_level', ['low', 'medium', 'high', 'blacklisted'])->default('low')->index();

            // Automated Courier Logistics
            $table->string('courier_code', 30)->nullable();
            $table->string('courier_consignment_id', 100)->nullable()->index();
            $table->string('courier_tracking_code', 100)->nullable()->index();

            // Statutory NBR Tax Compliance
            $table->string('mushak_6_3_number', 50)->nullable()->unique();

            // Milestone Timestamps
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamp('shipped_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
