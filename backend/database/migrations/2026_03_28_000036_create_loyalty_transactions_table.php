<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loyalty_points_id');
            $table->enum('transaction_type', ['earn', 'redeem', 'expire', 'admin_adjustment']);
            $table->integer('points_amount'); // positive for earn/adjust, negative for redeem/expire
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('loyalty_points_id')->references('id')->on('loyalty_points')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index('loyalty_points_id');
            $table->index('order_id');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
