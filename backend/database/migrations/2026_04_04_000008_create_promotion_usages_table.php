<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_rule_id')->constrained('promotion_rules')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('used_at');

            $table->index('promotion_rule_id');
            $table->index(['promotion_rule_id', 'user_id']);
            $table->index('used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
    }
};
