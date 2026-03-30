<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add Tap customer ID for recurring payment support
            // Nullable so existing users and old code that inserts users aren't affected
            $table->string('tap_customer_id')->nullable()->after('email');

            // Index for fast lookups by tap_customer_id (Tap webhooks may reference this)
            $table->index('tap_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex('users_tap_customer_id_index');

            // Drop the column
            $table->dropColumn('tap_customer_id');
        });
    }
};
