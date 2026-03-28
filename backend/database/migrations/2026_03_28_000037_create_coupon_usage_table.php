<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('discount_amount_fils'); // actual discount applied (immutable snapshot)
            $table->timestamp('used_at')->useCurrent();

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['coupon_id', 'order_id']); // PostgreSQL allows multiple NULLs — one use per order
            $table->index(['user_id', 'coupon_id']);
            $table->index(['coupon_id', 'used_at']);
            $table->index('used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usage');
    }
};
