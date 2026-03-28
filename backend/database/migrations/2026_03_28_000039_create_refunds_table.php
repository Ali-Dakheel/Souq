<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('tap_transaction_id')->nullable();
            $table->string('tap_refund_id')->nullable()->unique(); // null until Tap refund API is called
            $table->integer('refund_amount_fils');                 // supports partial refunds
            $table->enum('refund_reason', ['customer_request', 'order_cancelled', 'payment_error', 'duplicate_charge', 'other']);
            $table->text('refund_notes')->nullable();
            $table->enum('status', ['pending', 'initiated', 'completed', 'failed'])->default('pending');
            $table->jsonb('tap_response')->nullable();             // full Tap refund API response
            $table->unsignedBigInteger('processed_by_user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('tap_transaction_id')->references('id')->on('tap_transactions')->nullOnDelete();
            $table->foreign('processed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('order_id');
            $table->index('tap_transaction_id');
            $table->index('status');
            $table->index('created_at');
            $table->index('processed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
