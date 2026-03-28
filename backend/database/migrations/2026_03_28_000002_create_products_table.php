<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');           // {"ar": "...", "en": "..."}
            $table->string('slug')->unique();
            $table->jsonb('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->integer('base_price_fils');  // price in BHD fils (1 BHD = 1000 fils)
            $table->boolean('is_available')->default(true);
            $table->jsonb('images')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index('category_id');
            $table->index('is_available');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
