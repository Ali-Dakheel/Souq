<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('invoice_number', 30)->unique();
            $table->integer('subtotal_fils')->notNull();
            $table->integer('vat_fils')->notNull();
            $table->integer('discount_fils')->notNull()->default(0);
            $table->integer('total_fils')->notNull();
            $table->string('cr_number', 50)->notNull();
            $table->string('vat_number', 50)->notNull();
            $table->string('company_name_en')->notNull();
            $table->string('company_name_ar')->notNull();
            $table->text('company_address_en')->nullable();
            $table->text('company_address_ar')->nullable();
            $table->timestamp('issued_at')->notNull();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index('order_id');
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
