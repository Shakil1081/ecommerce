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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('hs_code', 30)->nullable(); // Statutory NBR/Customs tariff HS Code
            $table->string('origin_country')->default('Bangladesh'); // China, UAE, USA, Japan, Bangladesh, etc.
            $table->string('warranty_policy')->nullable(); // e.g. 1 Year Replacement Warranty
            $table->text('description')->nullable();
            $table->json('specifications')->nullable(); // Technical specs key-value map
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'is_featured']);
            $table->index('hs_code');
            $table->index('origin_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

