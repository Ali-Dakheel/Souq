<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();  // set null if product deleted
            $table->unsignedBigInteger('variant_id')->nullable();  // set null if variant deleted
            $table->string('sku');                                  // denormalized snapshot
            $table->jsonb('product_name');                         // {"ar": "...", "en": "..."} — immutable snapshot
            $table->jsonb('variant_attributes')->nullable();        // {"color": "red", "size": "L"} — immutable snapshot
            $table->integer('quantity');
            $table->integer('price_fils_per_unit');                // price at order time, immutable
            $table->integer('total_fils');                         // quantity × price_fils_per_unit
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('variants')->nullOnDelete();
            $table->index('order_id');
            $table->index('product_id');
            $table->index('variant_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
