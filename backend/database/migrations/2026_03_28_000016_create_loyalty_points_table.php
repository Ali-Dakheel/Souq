<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points_earned')->default(0);
            $table->integer('lifetime_points_redeemed')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('points_balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_points');
    }
};
