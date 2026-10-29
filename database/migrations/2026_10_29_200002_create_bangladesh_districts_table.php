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
        Schema::create('bangladesh_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('bangladesh_divisions')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_bn');
            $table->string('zone_type', 30)->default('outside_dhaka'); // inside_dhaka, dhaka_suburbs, outside_dhaka
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['division_id', 'name_en']);
            $table->index('zone_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bangladesh_districts');
    }
};

