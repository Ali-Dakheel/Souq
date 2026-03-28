<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_coverage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_zone_id');
            $table->string('governorate', 100); // must match customer_addresses.governorate
            $table->string('district', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->cascadeOnDelete();
            $table->unique(['delivery_zone_id', 'governorate', 'district']);
            $table->index('governorate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_coverage');
    }
};
