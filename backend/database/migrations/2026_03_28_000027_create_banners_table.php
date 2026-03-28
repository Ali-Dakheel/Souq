<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->jsonb('title');                     // {"ar": "...", "en": "..."}
            $table->text('image_url');
            $table->string('link_url', 500)->nullable();
            $table->enum('position', ['hero', 'sidebar', 'footer', 'modal'])->default('hero');
            $table->boolean('is_active')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('sort_order')->default(0);
            $table->jsonb('cta_text')->nullable();      // {"ar": "اشتر الآن", "en": "Buy Now"}
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
