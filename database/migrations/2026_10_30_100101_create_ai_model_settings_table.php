<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_settings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('provider', 32)->unique(); // gemini, openai, claude, ollama
            $table->string('name', 64);
            $table->boolean('is_active')->default(false)->index();
            $table->text('credentials'); // encrypted:array
            $table->string('default_model', 64);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(2048);
            $table->json('assigned_features')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_settings');
    }
};

