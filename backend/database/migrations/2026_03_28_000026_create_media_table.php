<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // original filename
            $table->string('file_name')->unique();      // UUID-based: "550e8400.jpg"
            $table->string('mime_type', 100);
            $table->integer('size_bytes');
            $table->string('disk', 50)->default('s3');
            $table->text('path');                       // relative path on disk
            $table->jsonb('alt_text')->nullable();      // {"ar": "...", "en": "..."}
            $table->string('mediable_type')->nullable(); // e.g. 'App\Modules\Catalog\Models\Product'
            $table->unsignedBigInteger('mediable_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id']);
            $table->index('disk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
