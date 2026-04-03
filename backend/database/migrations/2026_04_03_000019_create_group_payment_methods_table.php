<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_group_id');
            $table->string('payment_method', 50);

            // Foreign key with cascadeOnDelete
            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicate payment method entries
            $table->unique(['customer_group_id', 'payment_method']);

            // Indexes for fast lookups
            $table->index('customer_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_payment_methods');
    }
};
