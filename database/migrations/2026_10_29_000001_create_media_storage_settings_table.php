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
        Schema::create('media_storage_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('driver', 32)->index(); // local, s3, r2, gcs, minio
            $table->string('name', 128);
            $table->boolean('is_active')->default(false)->index();
            $table->text('credentials')->nullable(); // encrypted:array
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_storage_settings');
    }
};
