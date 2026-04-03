<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Add 'pending_collection' and 'collected' to old_status enum
            DB::statement('ALTER TABLE order_status_history DROP CONSTRAINT IF EXISTS order_status_history_old_status_check');
            DB::statement("ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_old_status_check CHECK (old_status IN ('pending','initiated','processing','paid','fulfilled','failed','refunded','cancelled','shipped','delivered','pending_collection','collected'))");

            // Add 'pending_collection' and 'collected' to new_status enum
            DB::statement('ALTER TABLE order_status_history DROP CONSTRAINT IF EXISTS order_status_history_new_status_check');
            DB::statement("ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_new_status_check CHECK (new_status IN ('pending','initiated','processing','paid','fulfilled','failed','refunded','cancelled','shipped','delivered','pending_collection','collected'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite: drop the enum CHECK constraints and recreate with new values
            DB::statement('PRAGMA foreign_keys=OFF');

            // Create a temporary table with the same structure
            DB::statement('CREATE TABLE order_status_history_temp AS SELECT * FROM order_status_history');

            // Drop the original table
            DB::statement('DROP TABLE order_status_history');

            // Recreate with varchar instead of enum to allow any value
            DB::statement('CREATE TABLE order_status_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                old_status VARCHAR(255) NULL,
                new_status VARCHAR(255) NOT NULL,
                changed_by VARCHAR(255) NULL,
                reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )');

            // Copy data back
            DB::statement('INSERT INTO order_status_history SELECT * FROM order_status_history_temp');

            // Drop temporary table
            DB::statement('DROP TABLE order_status_history_temp');

            // Recreate indexes
            DB::statement('CREATE INDEX order_status_history_order_id_created_at_index ON order_status_history(order_id, created_at)');
            DB::statement('CREATE INDEX order_status_history_new_status_index ON order_status_history(new_status)');

            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Cannot safely remove enum values — no-op
    }
};
