<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->jsonb('shipping_address_snapshot')->nullable()->after('shipping_address_id');
            $table->jsonb('billing_address_snapshot')->nullable()->after('billing_address_id');
            $table->string('coupon_code', 100)->nullable()->after('coupon_discount_fils');
            $table->index('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['coupon_code']);
            $table->dropColumn(['shipping_address_snapshot', 'billing_address_snapshot', 'coupon_code']);
        });
    }
};
