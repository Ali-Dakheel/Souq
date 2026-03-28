<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');           // {"ar": "اللون", "en": "Color"}
            $table->string('slug')->unique();
            $table->enum('attribute_type', ['color', 'size', 'material', 'brand', 'custom'])->default('custom');
            $table->enum('input_type', ['dropdown', 'color_picker', 'text', 'radio'])->default('dropdown');
            $table->boolean('is_filterable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_filterable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
