<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (! Schema::hasColumn('wishlists', 'share_token')) {
                $table->string('share_token', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('wishlists', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('wishlist_items', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (Schema::hasColumn('wishlists', 'share_token')) {
                $table->dropUnique(['share_token']);
                $table->dropColumn('share_token');
            }
            if (Schema::hasColumn('wishlists', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            if (Schema::hasColumn('wishlist_items', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
