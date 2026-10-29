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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('company_name_bn')->nullable();
            $table->enum('type', ['international', 'local'])->default('local')->index();
            $table->string('country_code', 3)->default('BD');
            $table->string('currency', 3)->default('BDT');
            $table->string('bin_number', 30)->nullable()->index();
            $table->string('tin_number', 30)->nullable()->index();
            $table->string('trade_license_number', 50)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('division_id')->nullable()->constrained('bangladesh_divisions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('bangladesh_districts')->nullOnDelete();
            $table->foreignId('thana_id')->nullable()->constrained('bangladesh_thanas')->nullOnDelete();
            $table->json('bank_details')->nullable();
            $table->string('payment_terms')->nullable();
            $table->boolean('is_vds_applicable')->default(false);
            $table->boolean('is_tds_applicable')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
