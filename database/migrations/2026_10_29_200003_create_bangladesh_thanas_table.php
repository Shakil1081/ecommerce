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
        Schema::create('bangladesh_thanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('bangladesh_districts')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_bn');
            $table->string('post_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['district_id', 'name_en']);
            $table->index('post_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bangladesh_thanas');
    }
};
