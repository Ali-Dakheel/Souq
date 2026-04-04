<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100);
            $table->boolean('is_exclusive')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_uses_global')->nullable();
            $table->integer('max_uses_per_user')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('priority');
            $table->index(['starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};
