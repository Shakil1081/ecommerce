<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_gateway_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('code', 50)->unique(); // steadfast, pathao, redx, paperfly, sundarban
            $table->string('name', 100);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_sandbox')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array
            $table->string('base_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->json('supported_features')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_gateway_settings');
    }
};
