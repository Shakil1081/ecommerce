<?php

declare(strict_types=1);

use App\Modules\Content\Models\StorePage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 150);
            $table->string('meta_description', 300)->nullable();
            $table->longText('body');
            $table->boolean('is_published')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed the legal / policy pages so they exist on every environment after `migrate`
        // (payment gateway merchant approval requires them to be live).
        StorePage::seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pages');
    }
};
