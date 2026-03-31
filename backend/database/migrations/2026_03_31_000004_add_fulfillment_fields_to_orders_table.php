<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('locale', 5)->default('ar')->after('notes');
            $table->string('tracking_number')->nullable()->after('locale');
            $table->timestamp('fulfilled_at')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'tracking_number', 'fulfilled_at']);
        });
    }
};
