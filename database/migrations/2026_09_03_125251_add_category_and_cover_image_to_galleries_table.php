<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 sub-step 3: `galleries` had no category concept and no explicit "cover image" — both are
 * additive, nullable columns rather than a schema rewrite. `category` is free text (like blog tags),
 * not a dedicated taxonomy table, matching the same lightweight-over-full-CRUD scoping decision as
 * sub-step 2's blog tags. `cover_image_id` is nullable + `nullOnDelete()` so deleting the chosen cover
 * image never blocks or cascades into deleting the album itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('category')->nullable()->after('title');
            $table->foreignId('cover_image_id')->nullable()->after('category')
                ->constrained('gallery_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_image_id');
            $table->dropColumn('category');
        });
    }
};
