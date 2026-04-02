<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_abandonments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->integer('cart_total_fils')->default(0);
            $table->integer('item_count')->default(0);
            $table->timestamp('abandoned_at');
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('carts')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('user_id');
            $table->index('session_id');
            $table->index('abandoned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_abandonments');
    }
};
