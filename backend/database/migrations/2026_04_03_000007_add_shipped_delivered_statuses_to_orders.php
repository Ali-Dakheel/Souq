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
        }
    }

    public function down(): void
    {
        // Cannot safely remove enum values — no-op
    }
};
