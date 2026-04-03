<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_group_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('customer_group_id');
            $table->unsignedInteger('price_fils');
            $table->unsignedInteger('compare_at_price_fils')->nullable();
            $table->timestamps();

            // Foreign keys with cascadeOnDelete
            $table->foreign('variant_id')
                ->references('id')
                ->on('variants')
                ->cascadeOnDelete();

            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicate pricing for same variant/group
            $table->unique(['variant_id', 'customer_group_id']);

            // Indexes for fast lookups
            $table->index('variant_id');
            $table->index('customer_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_group_prices');
    }
};
