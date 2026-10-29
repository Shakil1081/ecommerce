<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 16); // e.g. '6.3', '6.5', '6.6', '6.7', '6.8'
            $table->string('year_month', 6);     // e.g. '202610'
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'year_month'], 'mushak_seq_doc_ym_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_sequences');
    }
};
