<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->unique();
            $table->jsonb('attributes');     // {"color": "red", "size": "L"}
            $table->integer('price_fils')->nullable();   // variant-level override in fils; null = use product base_price_fils
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index('product_id');
            $table->index('sku');
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
