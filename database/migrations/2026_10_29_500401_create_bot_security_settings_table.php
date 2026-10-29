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
        Schema::create('bot_security_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['recaptcha_v2', 'recaptcha_v3', 'turnstile'])->default('recaptcha_v3');
            $table->string('site_key', 255);
            $table->text('secret_key');
            $table->boolean('is_active')->default(false)->index();
            $table->decimal('score_threshold', 3, 2)->default(0.50);
            $table->json('pages_enabled');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_security_settings');
    }
};
