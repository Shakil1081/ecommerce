<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('driver', 50)->default('smtp');
            $table->string('host', 255)->nullable();
            $table->unsignedInteger('port')->default(587);
            $table->string('encryption', 20)->nullable(); // tls, ssl, null
            $table->string('username', 255)->nullable();
            $table->text('password')->nullable(); // encrypted
            $table->string('from_address', 255)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
