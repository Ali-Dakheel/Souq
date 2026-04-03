<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Add 'cod' to payment_method enum
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('benefit','benefit_pay_qr','card','apple_pay','cod'))");

            // Add 'pending_collection' and 'collected' to order_status enum
            // Full list includes all values from original migration + shipped/delivered (000007) + new ones
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_status_check CHECK (order_status IN ('pending','initiated','processing','paid','fulfilled','failed','refunded','cancelled','shipped','delivered','pending_collection','collected'))");
        }
        // For SQLite, migration 000007 already converted the table to use VARCHAR instead of enum
        // So no further changes needed here
    }

    public function down(): void
    {
        // Cannot safely remove enum values — no-op
    }
};
