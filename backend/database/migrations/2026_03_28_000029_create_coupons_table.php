<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();          // stored uppercase
            $table->jsonb('name');                         // {"ar": "...", "en": "..."}
            $table->jsonb('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_fils']);
            $table->integer('discount_value');             // 0–100 for percentage, fils for fixed
            $table->integer('minimum_order_amount_fils')->default(0);
            $table->integer('maximum_discount_fils')->nullable(); // cap for percentage discounts
            $table->integer('max_uses_global')->nullable();
            $table->integer('max_uses_per_user')->nullable()->default(1);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->enum('applicable_to', ['all_products', 'specific_categories', 'specific_products'])->default('all_products');
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index(['starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
