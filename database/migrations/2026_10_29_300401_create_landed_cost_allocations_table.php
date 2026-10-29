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
        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('allocation_number')->unique();
            $table->date('allocation_date');
            $table->enum('reference_type', ['import', 'local'])->index();
            $table->foreignId('import_purchase_order_id')->nullable()->constrained('import_purchase_orders')->cascadeOnDelete();
            $table->foreignId('bill_of_entry_id')->nullable()->constrained('bills_of_entry')->nullOnDelete();
            $table->foreignId('local_purchase_order_id')->nullable()->constrained('local_purchase_orders')->cascadeOnDelete();
            $table->decimal('base_purchase_cost_bdt', 14, 4);
            $table->decimal('freight_cost_bdt', 14, 4)->default(0.0000);
            $table->decimal('insurance_cost_bdt', 14, 4)->default(0.0000);
            $table->decimal('port_cnf_cost_bdt', 14, 4)->default(0.0000);
            $table->decimal('customs_duties_bdt', 14, 4)->default(0.0000);
            $table->decimal('transport_carrying_bdt', 14, 4)->default(0.0000);
            $table->decimal('handling_cost_bdt', 14, 4)->default(0.0000);
            $table->decimal('other_costs_bdt', 14, 4)->default(0.0000);
            $table->decimal('total_landed_cost_bdt', 14, 4);
            $table->json('allocated_items_breakdown')->nullable();
            $table->enum('status', ['draft', 'allocated', 'applied_to_inventory'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landed_cost_allocations');
    }
};
