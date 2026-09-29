<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin mark individual gallery images for the homepage bridal slider.
 *
 * Only ONE new column, deliberately. The obvious alternative was to add a second
 * `homepage_sort` alongside it, but `gallery_images.sort` already exists, is already indexed, is
 * already what `Gallery::images()` orders by, and already has a working admin reorder UI (the
 * arrow buttons in Gallery/Form.tsx, POSTing to `gallery.images.reorder`). Adding a parallel
 * ordering would have meant a second reorder endpoint and a second set of controls that could
 * silently disagree with the first. The slider therefore orders by `galleries.sort` then
 * `gallery_images.sort`, so the existing arrows genuinely re-order slider priority — see
 * App\Support\FeaturedGalleryImages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->boolean('show_on_homepage')->default(false)->after('caption');

            // The homepage queries exclusively on this flag on every page load, and the vast
            // majority of rows will be false.
            $table->index('show_on_homepage');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->dropIndex(['show_on_homepage']);
            $table->dropColumn('show_on_homepage');
        });
    }
};
