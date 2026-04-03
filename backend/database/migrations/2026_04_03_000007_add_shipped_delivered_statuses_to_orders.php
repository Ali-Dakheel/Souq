<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_status_check CHECK (order_status IN ('pending','initiated','processing','paid','fulfilled','failed','refunded','cancelled','shipped','delivered'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite: drop the original enum CHECK constraint and recreate with new values
            // We'll do this by dropping the constraint name reference manually if possible,
            // but SQLite doesn't support named constraints, so we just let the enum be a string
            DB::statement('DROP TABLE IF EXISTS orders_temp');
            // Since SQLite doesn't support dropping constraints, and we can't modify the enum,
            // we'll work around this by using PRAGMA to disable and re-enable foreign keys
            DB::statement('PRAGMA foreign_keys=OFF');

            // Create a temporary table with the same structure but without the enum check
            DB::statement('CREATE TABLE orders_temp AS SELECT * FROM orders');

            // Drop the original table
            DB::statement('DROP TABLE orders');

            // Recreate with varchar instead of enum to allow any value
            DB::statement('CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                guest_email VARCHAR(255) NULL,
                order_status VARCHAR(255) DEFAULT "pending" NOT NULL,
                subtotal_fils INTEGER NOT NULL,
                coupon_discount_fils INTEGER DEFAULT 0,
                coupon_code VARCHAR(255) NULL,
                vat_fils INTEGER NOT NULL,
                delivery_fee_fils INTEGER DEFAULT 0,
                total_fils INTEGER NOT NULL,
                payment_method VARCHAR(255) NULL,
                shipping_address_id BIGINT UNSIGNED NULL,
                shipping_address_snapshot JSON NULL,
                billing_address_id BIGINT UNSIGNED NULL,
                billing_address_snapshot JSON NULL,
                delivery_zone_id BIGINT UNSIGNED NULL,
                delivery_method_id BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                locale VARCHAR(10) DEFAULT "ar",
                tracking_number VARCHAR(255) NULL,
                fulfilled_at TIMESTAMP NULL,
                paid_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (shipping_address_id) REFERENCES customer_addresses(id) ON DELETE SET NULL,
                FOREIGN KEY (billing_address_id) REFERENCES customer_addresses(id) ON DELETE SET NULL
            )');

            // Copy data back
            DB::statement('INSERT INTO orders SELECT * FROM orders_temp');

            // Drop temporary table
            DB::statement('DROP TABLE orders_temp');

            // Recreate indexes
            DB::statement('CREATE INDEX orders_user_id_created_at_index ON orders(user_id, created_at)');
            DB::statement('CREATE INDEX orders_order_status_index ON orders(order_status)');
            DB::statement('CREATE INDEX orders_guest_email_index ON orders(guest_email)');
            DB::statement('CREATE INDEX orders_created_at_index ON orders(created_at)');
            DB::statement('CREATE INDEX orders_paid_at_index ON orders(paid_at)');

            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Cannot safely remove enum values — no-op
    }
};
