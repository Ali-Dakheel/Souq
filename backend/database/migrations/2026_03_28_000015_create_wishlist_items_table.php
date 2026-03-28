<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wishlist_id');
            $table->unsignedBigInteger('variant_id');
            $table->timestamp('added_at')->useCurrent();

            $table->foreign('wishlist_id')->references('id')->on('wishlists')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('variants')->cascadeOnDelete();
            $table->unique(['wishlist_id', 'variant_id']);
            $table->index('variant_id');
            $table->index('added_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
