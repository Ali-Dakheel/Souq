<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');           // {"ar": "التوصيل السريع", "en": "Express Delivery"}
            $table->string('slug')->unique();
            $table->jsonb('description')->nullable();
            $table->enum('delivery_method_type', ['standard', 'express', 'same_day', 'pickup'])->default('standard');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('delivery_method_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_methods');
    }
};
