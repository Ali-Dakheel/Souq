<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_closures', function (Blueprint $table) {
            $table->id();
            $table->enum('closure_type', ['holiday', 'maintenance', 'ramadan_special']);
            $table->jsonb('name');           // {"ar": "عيد الأضحى", "en": "Eid Al-Adha"}
            $table->date('starts_at');
            $table->date('ends_at');         // inclusive
            $table->time('opens_at')->nullable();  // for ramadan_special custom hours
            $table->time('closes_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_closures');
    }
};
