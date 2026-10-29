<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_consignments', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('courier_code', 50)->index();
            $table->string('consignment_id', 100)->nullable()->index();
            $table->string('tracking_code', 100)->nullable()->index();
            $table->string('status', 50)->default('pending')->index(); // pending, booked, in_transit, out_for_delivery, delivered, partial_delivered, returned, cancelled, failed
            $table->string('delivery_zone', 50)->nullable()->index(); // inside_dhaka, dhaka_suburbs, outside_dhaka
            $table->decimal('delivery_fee_bdt', 14, 4)->default(0.0000);
            $table->decimal('cod_amount_bdt', 14, 4)->default(0.0000);
            $table->string('recipient_name');
            $table->string('recipient_phone', 30)->index();
            $table->text('recipient_address');
            $table->text('courier_notes')->nullable();
            $table->json('booking_response_payload')->nullable();
            $table->json('last_webhook_payload')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_consignments');
    }
};
