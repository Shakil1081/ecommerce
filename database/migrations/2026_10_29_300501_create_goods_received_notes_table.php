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
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('grn_number')->unique();
            $table->date('grn_date');
            $table->enum('source_type', ['import', 'local'])->index();
            $table->foreignId('import_purchase_order_id')->nullable()->constrained('import_purchase_orders')->nullOnDelete();
            $table->foreignId('local_purchase_order_id')->nullable()->constrained('local_purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier_challan_number')->nullable()->index();
            $table->string('truck_or_container_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->enum('status', [
                'draft',
                'in_inspection',
                'completed',
                'discrepancy_flagged',
            ])->default('draft')->index();
            $table->unsignedInteger('total_ordered_quantity')->default(0);
            $table->unsignedInteger('total_received_quantity')->default(0);
            $table->unsignedInteger('total_damaged_quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_received_notes');
    }
};
