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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->enum('status', ['active', 'abandoned', 'converted'])->default('active')->index();
            $table->string('coupon_code', 50)->nullable()->index();
            $table->decimal('subtotal_bdt', 14, 4)->default(0.0000);
            $table->decimal('discount_amount_bdt', 14, 4)->default(0.0000);
            $table->decimal('total_bdt', 14, 4)->default(0.0000);
            $table->unsignedInteger('total_items_count')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
