<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop stub tables in FK-safe order (before dropping columns from orders)
        Schema::dropIfExists('delivery_method_zone_pricing');
        Schema::dropIfExists('zone_coverage');
        Schema::dropIfExists('delivery_methods');
        Schema::dropIfExists('delivery_zones');

        // 2. Drop columns from orders (SQLite doesn't support dropForeign by name)
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['delivery_zone_id']);
                $table->dropForeign(['delivery_method_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_zone_id', 'delivery_method_id']);
        });
    }

    public function down(): void
    {
        // Recreate delivery_zones and delivery_methods
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->string('slug')->unique();
            $table->jsonb('description')->nullable();
            $table->string('delivery_method_type')->default('standard');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('zone_coverage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_zone_id');
            $table->string('governorate', 100);
            $table->string('district', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->cascadeOnDelete();
            $table->unique(['delivery_zone_id', 'governorate', 'district']);
            $table->index('governorate');
        });

        Schema::create('delivery_method_zone_pricing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_method_id');
            $table->unsignedBigInteger('delivery_zone_id');
            $table->foreign('delivery_method_id')->references('id')->on('delivery_methods')->cascadeOnDelete();
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->cascadeOnDelete();
            $table->unique(['delivery_method_id', 'delivery_zone_id']);
            $table->index('delivery_zone_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->unsignedBigInteger('delivery_method_id')->nullable();
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->nullOnDelete();
            $table->foreign('delivery_method_id')->references('id')->on('delivery_methods')->nullOnDelete();
        });
    }
};
