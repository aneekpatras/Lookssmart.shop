<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ad hoc task 20: replaces `galleries.category` (free text) with a real `gallery_category_id` FK
 * into the new `gallery_categories` table, so categories can carry their own admin-defined display
 * order. Every distinct existing `category` string is backfilled into a real `GalleryCategory` row
 * first (ordered by that string's lowest existing album `sort`, so today's implicit ordering
 * becomes tomorrow's explicit one) before the old string column is dropped — a one-way data
 * migration; `down()` restores the column shape but not the original string values, the same
 * documented trade-off already used elsewhere in this project for superseded free-text columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('gallery_category_id')->nullable()->after('category')
                ->constrained('gallery_categories')->nullOnDelete();
        });

        $distinctCategories = DB::table('galleries')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->selectRaw('category, MIN(sort) as min_sort')
            ->groupBy('category')
            ->orderBy('min_sort')
            ->get();

        $usedSlugs = [];

        foreach ($distinctCategories as $index => $row) {
            $slug = Str::slug($row->category) ?: 'category';
            $baseSlug = $slug;
            $suffix = 2;
            while (in_array($slug, $usedSlugs, true)) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }
            $usedSlugs[] = $slug;

            $categoryId = DB::table('gallery_categories')->insertGetId([
                'name' => $row->category,
                'slug' => $slug,
                'sort' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('galleries')->where('category', $row->category)->update([
                'gallery_category_id' => $categoryId,
            ]);
        }

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('category')->nullable()->after('title');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_category_id');
        });
    }
};
