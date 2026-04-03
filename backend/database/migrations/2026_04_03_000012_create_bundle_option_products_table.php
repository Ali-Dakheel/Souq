<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_option_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_option_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedSmallInteger('default_quantity')->default(1);
            $table->unsignedSmallInteger('min_quantity')->default(1);
            $table->unsignedSmallInteger('max_quantity')->default(1);
            $table->unsignedInteger('price_override_fils')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->foreign('bundle_option_id')->references('id')->on('bundle_options')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['bundle_option_id', 'product_id']);
            $table->index('bundle_option_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_option_products');
    }
};
