<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloadable_link_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('downloadable_link_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedSmallInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('downloadable_link_id')->references('id')->on('downloadable_links')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unique(['downloadable_link_id', 'order_item_id']);
            $table->index('downloadable_link_id');
            $table->index('order_item_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloadable_link_purchases');
    }
};
