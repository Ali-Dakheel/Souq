<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->jsonb('title')->nullable();    // {"ar": "...", "en": "..."}
            $table->text('body')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_email')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->integer('unhelpful_count')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['product_id', 'is_approved', 'created_at']);
            $table->index('user_id');
            $table->index('rating');
            $table->index('is_approved');
            $table->index('is_verified_purchase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
