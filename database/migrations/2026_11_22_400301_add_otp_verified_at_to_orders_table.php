<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Set when the customer confirms the medium/high-risk order with the SMS OTP (AGENTS.md §8)
            $table->timestamp('otp_verified_at')->nullable()->after('fraud_risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('otp_verified_at');
        });
    }
};
