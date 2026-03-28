<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_method_zone_pricing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_method_id');
            $table->unsignedBigInteger('delivery_zone_id');
            $table->integer('base_fee_fils');                         // flat delivery fee in fils
            $table->integer('min_free_delivery_amount_fils')->nullable(); // free if order ≥ this amount
            $table->integer('estimated_days');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->foreign('delivery_method_id')->references('id')->on('delivery_methods')->cascadeOnDelete();
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->cascadeOnDelete();
            $table->unique(['delivery_method_id', 'delivery_zone_id']);
            $table->index('delivery_zone_id');
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_method_zone_pricing');
    }
};
