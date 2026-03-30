<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->after('processed_by_user_id');
            $table->text('customer_notes')->nullable()->after('refund_notes');
            $table->text('admin_notes')->nullable()->after('customer_notes');

            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('requested_by_user_id');
        });

        // Update the status enum to include new values (PostgreSQL only — SQLite has no CHECK constraints)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS refunds_status_check');
            DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'initiated'::text, 'approved'::text, 'rejected'::text, 'processing'::text, 'completed'::text, 'failed'::text]))");
        }
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('refunds_requested_by_user_id_index');
            $table->dropForeign('refunds_requested_by_user_id_foreign');
            $table->dropColumn(['requested_by_user_id', 'customer_notes', 'admin_notes']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS refunds_status_check');
            DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'initiated'::text, 'completed'::text, 'failed'::text]))");
        }
    }
};
