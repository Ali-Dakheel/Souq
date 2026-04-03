<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_shipping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->restrictOnDelete();
            $table->string('carrier', 100);
            $table->string('method_name_en');
            $table->string('method_name_ar');
            $table->integer('rate_fils');
            $table->string('tracking_number')->nullable();
            $table->timestamps();

            $table->index('carrier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipping');
    }
};
