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
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 32)->unique()->index(); // bkash, nagad, sslcommerz, stripe, cod, bank_transfer
            $table->string('name', 100);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_sandbox')->default(true);
            $table->text('credentials')->nullable(); // encrypted:array
            $table->decimal('surcharge_percentage', 5, 2)->default(0.00);
            $table->decimal('min_order_amount', 14, 4)->nullable();
            $table->decimal('max_order_amount', 14, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->text('instructions')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // successful, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
