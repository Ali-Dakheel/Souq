<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('old_status', ['pending', 'initiated', 'processing', 'paid', 'fulfilled', 'failed', 'refunded', 'cancelled'])->nullable();
            $table->enum('new_status', ['pending', 'initiated', 'processing', 'paid', 'fulfilled', 'failed', 'refunded', 'cancelled']);
            $table->string('changed_by')->nullable(); // "system", "admin", user email, etc.
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['order_id', 'created_at']);
            $table->index('new_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
