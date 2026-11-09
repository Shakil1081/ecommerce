<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change model_id from unsignedBigInteger to string (VARCHAR 255)
     * to support both standard integer IDs and ULID/UUID models (like Product).
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('model_id', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
        });
    }
};

