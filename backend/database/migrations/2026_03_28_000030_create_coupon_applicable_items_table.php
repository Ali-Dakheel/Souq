<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_applicable_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->enum('itemable_type', ['category', 'product']);
            $table->unsignedBigInteger('itemable_id'); // category_id or product_id; no DB FK (polymorphic)
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->unique(['coupon_id', 'itemable_type', 'itemable_id']);
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_applicable_items');
    }
};
