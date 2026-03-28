<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tap_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('tap_charge_id')->nullable()->unique(); // null until Tap API is called
            $table->integer('amount_fils');                        // immutable, locked at creation
            $table->string('currency', 3)->default('BHD');
            $table->enum('status', ['pending', 'initiated', 'captured', 'failed', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['benefit', 'benefit_pay_qr', 'card', 'apple_pay'])->nullable();
            $table->string('source_id')->nullable();              // Tap source ID
            $table->jsonb('tap_response')->nullable();            // full Tap API response for debugging
            $table->text('failure_reason')->nullable();           // Tap decline reason
            $table->text('redirect_url')->nullable();             // hosted payment redirect
            $table->timestamp('webhook_received_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index('status');
            $table->index('created_at');
            $table->index('webhook_received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tap_transactions');
    }
};
