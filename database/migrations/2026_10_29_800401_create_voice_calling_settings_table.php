<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_calling_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('provider', 50)->default('twilio'); // twilio, retell, vapi
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_sandbox')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array
            $table->string('caller_id', 50)->nullable();
            $table->string('language', 10)->default('bn-BD');
            $table->boolean('dtmf_enabled')->default(true);
            $table->boolean('speech_enabled')->default(true);
            $table->unsignedSmallInteger('retry_attempts')->default(3);
            $table->text('script_template_bn')->nullable();
            $table->text('script_template_en')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_calling_settings');
    }
};
