<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_call_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('phone_number', 20)->index();
            $table->string('call_sid', 100)->nullable()->index();
            $table->string('provider', 50)->default('twilio');
            $table->string('status', 50)->default('initiated')->index(); // initiated, ringing, answered, completed, busy, no_answer, failed
            $table->string('verification_result', 50)->nullable()->index(); // confirmed, cancelled, unattended, failed
            $table->string('response_mode', 20)->nullable(); // dtmf, speech
            $table->string('dtmf_digit', 5)->nullable();
            $table->string('speech_intent', 50)->nullable();
            $table->string('recording_url', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_call_logs');
    }
};
