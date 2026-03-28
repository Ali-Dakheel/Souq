<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('nationality', 2)->nullable(); // ISO 3166-1 alpha-2 e.g. "BH"
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('preferred_locale', ['ar', 'en'])->default('ar');
            $table->boolean('marketing_consent')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
