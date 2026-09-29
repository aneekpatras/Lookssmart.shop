<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same field, same precedence rule, same reasoning as `stock_image_url` on `service_categories`
 * (see `2026_09_08_090000_add_stock_image_url_to_service_categories_table`) — just one level down,
 * on the service itself: an admin can paste a remote image URL instead of uploading a file. A
 * service's own uploaded medialibrary image still always wins over this when both are present; see
 * `ServiceController::applyImage()`.
 *
 * Unlike the category-level column, this is not scoped to a single trusted stock-photo host — the
 * admin panel now accepts an arbitrary URL, so `SecurityHeaders::buildCsp()`'s `img-src` was widened
 * from an explicit host allowlist to `https:` at the same time this shipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('stock_image_url', 2048)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('stock_image_url');
        });
    }
};
