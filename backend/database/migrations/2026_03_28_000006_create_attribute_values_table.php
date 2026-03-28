<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->jsonb('name');           // {"ar": "أحمر", "en": "Red"}
            $table->string('value_key');     // "red", "L", "cotton"
            $table->string('display_value')->nullable(); // hex color "#FF0000" or swatch path
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->unique(['attribute_id', 'value_key']);
            $table->index('attribute_id');
            $table->index('value_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
