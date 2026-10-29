<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            if (! Schema::hasColumn('carts', 'customer_phone')) {
                $table->string('customer_name', 100)->nullable()->after('guest_token');
                $table->string('customer_phone', 20)->nullable()->index()->after('customer_name');
                $table->string('customer_email', 255)->nullable()->index()->after('customer_phone');
            }
        });

        Schema::create('cart_recovery_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 20)->nullable()->index();
            $table->string('customer_email', 255)->nullable()->index();
            $table->unsignedSmallInteger('step')->default(1)->index(); // 1 = 1hr, 2 = 6hr, 3 = 24hr
            $table->string('channel', 20)->default('sms'); // sms, whatsapp, email
            $table->string('status', 20)->default('sent')->index(); // sent, failed, recovered
            $table->string('incentive_coupon_code', 50)->nullable();
            $table->foreignId('recovered_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('dispatched_at')->index();
            $table->timestamp('recovered_at')->nullable()->index();
            $table->text('message_content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_recovery_logs');

        Schema::table('carts', function (Blueprint $table): void {
            if (Schema::hasColumn('carts', 'customer_phone')) {
                $table->dropColumn(['customer_name', 'customer_phone', 'customer_email']);
            }
        });
    }
};
