<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('shipment_number', 30)->unique();
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number')->nullable();
            $table->enum('status', ['pending', 'shipped', 'delivered'])->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('order_id');
            $table->index('status');
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
