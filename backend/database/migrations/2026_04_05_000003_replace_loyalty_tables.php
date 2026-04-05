<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old stub tables (never used — replaced by proper Loyalty module)
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_points');

        // loyalty_accounts: one per user, tracks point balance
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points_earned')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('points_balance');
        });

        // loyalty_transactions: every earn/redeem/adjust/expire/store_credit movement
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loyalty_account_id');
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust', 'store_credit']);
            $table->integer('points'); // positive=earn, negative=redeem/expire
            $table->string('reference_type')->nullable(); // 'order', 'return', 'admin'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description_en');
            $table->string('description_ar');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('loyalty_account_id')->references('id')->on('loyalty_accounts')->cascadeOnDelete();
            $table->index('loyalty_account_id');
            $table->index('type');
            $table->index('created_at');
        });

        // loyalty_config: key-value store for earn/redeem rates
        Schema::create('loyalty_config', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_config');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');

        // Restore old tables
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points_earned')->default(0);
            $table->integer('lifetime_points_redeemed')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loyalty_points_id');
            $table->enum('transaction_type', ['earn', 'redeem', 'expire', 'admin_adjustment']);
            $table->integer('points_amount');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('loyalty_points_id')->references('id')->on('loyalty_points')->cascadeOnDelete();
        });
    }
};
