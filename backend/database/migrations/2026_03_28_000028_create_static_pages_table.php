<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // 'privacy-policy', 'terms-of-service', etc.
            $table->jsonb('title');           // {"ar": "...", "en": "..."}
            $table->jsonb('content');         // {"ar": "...", "en": "..."} — HTML allowed
            $table->jsonb('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_required')->default(false); // Bahrain compliance pages
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('is_published');
            $table->index('is_required');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
