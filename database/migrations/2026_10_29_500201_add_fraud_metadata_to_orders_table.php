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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('customer_email')->index();
            $table->string('device_fingerprint', 100)->nullable()->after('ip_address')->index();
            $table->text('user_agent')->nullable()->after('device_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['device_fingerprint']);
            $table->dropColumn(['ip_address', 'device_fingerprint', 'user_agent']);
        });
    }
};
