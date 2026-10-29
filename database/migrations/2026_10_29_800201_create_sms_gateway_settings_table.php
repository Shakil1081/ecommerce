<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_gateway_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('code', 50)->unique(); // greenweb, reve, twilio
            $table->string('name', 100);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_sandbox')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array
            $table->string('sender_id', 50)->nullable();
            $table->decimal('balance_bdt', 14, 4)->nullable();
            $table->timestamp('balance_updated_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_gateway_settings');
    }
};
