<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_rule_id')->constrained('promotion_rules')->cascadeOnDelete();
            $table->string('type');
            $table->string('operator');
            $table->jsonb('value');

            $table->index('promotion_rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_conditions');
    }
};
