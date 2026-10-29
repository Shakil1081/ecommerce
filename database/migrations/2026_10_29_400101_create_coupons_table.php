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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 50)->unique()->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'free_shipping'])->default('percentage')->index();
            $table->decimal('discount_value', 14, 4)->default(0.0000);
            $table->decimal('min_order_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('max_discount_amount_bdt', 14, 4)->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->default(1);
            $table->unsignedInteger('total_used_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
