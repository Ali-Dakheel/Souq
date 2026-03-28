<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('address_type', ['shipping', 'billing'])->default('shipping');
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('governorate', 100); // Manama, Muharraq, Al Rifaa, etc.
            $table->string('district', 100)->nullable();
            $table->text('street_address');
            $table->string('building_number', 50)->nullable();
            $table->string('apartment_number', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'address_type']);
            $table->index(['user_id', 'is_default']);
            $table->index('governorate');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
