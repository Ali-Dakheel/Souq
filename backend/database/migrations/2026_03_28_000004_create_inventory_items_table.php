<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id')->unique();
            $table->integer('quantity_available')->default(0);
            $table->integer('quantity_reserved')->default(0);   // held by pending orders
            $table->integer('low_stock_threshold')->default(5);
            $table->timestamps();

            $table->foreign('variant_id')->references('id')->on('variants')->cascadeOnDelete();
            $table->index('quantity_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
