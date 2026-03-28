<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique(); // 'store_name', 'vat_number', etc.
            $table->jsonb('setting_value')->nullable();
            $table->enum('value_type', ['string', 'json', 'integer', 'boolean', 'array'])->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_mutable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
