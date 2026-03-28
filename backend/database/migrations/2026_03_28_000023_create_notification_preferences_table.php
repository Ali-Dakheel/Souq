<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('order_confirmations_email')->default(true);
            $table->boolean('order_updates_email')->default(true);
            $table->boolean('promotional_email')->default(false);
            $table->boolean('order_confirmations_sms')->default(false);
            $table->boolean('order_updates_sms')->default(false);
            $table->boolean('promotional_sms')->default(false);
            $table->boolean('push_notifications')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
