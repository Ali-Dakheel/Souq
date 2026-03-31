<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique(); // "ORD-2026-00001"
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_email')->nullable();
            $table->enum('order_status', ['pending', 'initiated', 'processing', 'paid', 'fulfilled', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->integer('subtotal_fils');                       // sum of items × qty, before discounts/tax
            $table->integer('coupon_discount_fils')->default(0);    // discount amount, always positive
            $table->integer('vat_fils');                            // 10% VAT for Bahrain
            $table->integer('delivery_fee_fils')->default(0);
            $table->integer('total_fils');                          // final charged amount
            $table->enum('payment_method', ['benefit', 'benefit_pay_qr', 'card', 'apple_pay'])->nullable();
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->unsignedBigInteger('billing_address_id')->nullable();
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->unsignedBigInteger('delivery_method_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shipping_address_id')->references('id')->on('customer_addresses')->nullOnDelete();
            $table->foreign('billing_address_id')->references('id')->on('customer_addresses')->nullOnDelete();
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->nullOnDelete();
            $table->foreign('delivery_method_id')->references('id')->on('delivery_methods')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index('order_status');
            $table->index('guest_email');
            $table->index('created_at');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
