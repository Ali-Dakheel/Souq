<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('order_item_id');
            $table->integer('quantity_shipped');

            $table->foreign('shipment_id')->references('id')->on('shipments')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();

            $table->index('shipment_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
