<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('name_en')->notNull();
            $table->string('name_ar')->notNull();
            $table->string('sku', 100)->notNull();
            $table->integer('quantity')->notNull();
            $table->integer('unit_price_fils')->notNull();
            $table->unsignedSmallInteger('vat_rate')->notNull()->default(10); // 10 = 10% VAT; stored as integer percentage per project no-decimal convention
            $table->integer('vat_fils')->notNull();
            $table->integer('total_fils')->notNull();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('variants')->nullOnDelete();
            $table->index('invoice_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
