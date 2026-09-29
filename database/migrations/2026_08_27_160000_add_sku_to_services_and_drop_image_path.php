<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('service_category_id');
        });

        // Superseded by spatie/laravel-medialibrary's own `media` table (Phase 6 sub-step 1) —
        // confirmed unused in any seeder/factory before dropping.
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('sku');
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });
    }
};
