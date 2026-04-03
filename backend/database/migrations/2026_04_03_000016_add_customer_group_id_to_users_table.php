<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable to allow existing users and old code that inserts users
            // Zero-downtime safe: no locking required for nullable column with no default
            $table->unsignedBigInteger('customer_group_id')->nullable()->after('email');

            // Foreign key constraint with nullOnDelete for zero-downtime safety
            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->nullOnDelete();

            // Index for fast lookups
            $table->index('customer_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign('users_customer_group_id_foreign');

            // Drop index
            $table->dropIndex('users_customer_group_id_index');

            // Drop column
            $table->dropColumn('customer_group_id');
        });
    }
};
