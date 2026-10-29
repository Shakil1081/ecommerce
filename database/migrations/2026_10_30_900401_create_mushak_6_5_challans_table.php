<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushak_6_5_challans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('challan_number', 32)->unique(); // M6.5-YYYYMM-XXXXXX
            $table->string('tax_period', 7)->index();       // YYYY-MM
            $table->dateTime('issue_date');

            // Linked Warehouse Transfer (transfer uses ULID primary key)
            $table->foreignUlid('warehouse_transfer_id')->nullable()->unique()->constrained('warehouse_transfers')->nullOnDelete();

            // Dispatching Facility (প্রেরণকারী ডিপো)
            $table->foreignId('source_warehouse_id')->constrained('warehouses');
            $table->string('source_warehouse_name');
            $table->string('source_warehouse_bin', 32)->index();
            $table->text('source_warehouse_address');

            // Receiving Facility (গ্রহণকারী ডিপো)
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->string('destination_warehouse_name');
            $table->string('destination_warehouse_bin', 32)->index();
            $table->text('destination_warehouse_address');

            // Transport & Carrier Particulars (যানবাহনের বিবরণ)
            $table->string('vehicle_type_and_number');
            $table->string('driver_name')->nullable();
            $table->string('driver_contact', 32)->nullable();
            $table->dateTime('dispatch_time');
            $table->dateTime('arrival_time')->nullable();

            // Totals
            $table->unsignedInteger('total_quantity')->default(0);
            $table->decimal('total_valuation_bdt', 14, 4)->default(0);

            // Status & Audit
            $table->string('status', 24)->default('dispatched'); // dispatched, received, cancelled
            $table->string('integrity_hash', 64);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mushak_6_5_challans');
    }
};
