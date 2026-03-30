<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tap_transactions', function (Blueprint $table) {
            // Drop the unique constraint on order_id to allow multiple payment attempts per order
            // This is safe: old code that queries by order_id will still work (just return the first/latest)
            $table->dropUnique('tap_transactions_order_id_unique');

            // Add attempt_number to track which attempt this is (1, 2, 3, etc.)
            // Default to 1 so old records (before this migration) are treated as first attempt
            $table->integer('attempt_number')->default(1)->after('order_id');

            // Composite index: order_id + attempt_number for fast lookups
            // This replaces the unique constraint with a regular index to allow duplicates
            $table->index(['order_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::table('tap_transactions', function (Blueprint $table) {
            // Remove the composite index
            $table->dropIndex('tap_transactions_order_id_attempt_number_index');

            // Remove the attempt_number column
            $table->dropColumn('attempt_number');

            // Restore the unique constraint on order_id
            // Note: If multiple tap_transactions exist for the same order_id,
            // this will fail. Migration is one-way without manual cleanup first.
            $table->unique('order_id');
        });
    }
};
