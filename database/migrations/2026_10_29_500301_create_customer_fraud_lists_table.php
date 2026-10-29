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
        Schema::create('customer_fraud_lists', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['blacklist', 'whitelist'])->default('blacklist')->index();
            $table->string('phone_number', 20)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('device_fingerprint', 100)->nullable()->index();
            $table->string('customer_name', 150)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_fraud_lists');
    }
};
