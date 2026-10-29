<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('phone_number_id', 100)->nullable();
            $table->string('business_account_id', 100)->nullable();
            $table->text('access_token')->nullable(); // encrypted
            $table->string('webhook_verify_token', 100)->nullable();
            $table->text('app_secret')->nullable(); // encrypted
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_sandbox')->default(false);
            $table->string('api_version', 20)->default('v21.0');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
