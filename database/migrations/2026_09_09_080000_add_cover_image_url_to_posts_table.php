<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second, EXTERNAL cover image slot for posts, alongside the existing `cover_image_path` (a
 * local `storage/app/public` path written by `SecureUploadService::storePublicImage()` when an
 * admin uploads a real cover through the blog form).
 *
 * `cover_image_path` cannot represent a stock photo URL or a `public/images/` asset — it is always
 * resolved via `asset("storage/{$path}")` against the `public` disk. This mirrors
 * `service_categories.stock_image_url` (Phase 14 catalog overhaul): a nullable external URL used as
 * seeded/fallback imagery, with the admin's own uploaded `cover_image_path` always taking
 * precedence the moment a real photo is uploaded — see `PublicWebsiteController::postCoverUrl()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('cover_image_url', 2048)->nullable()->after('cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('cover_image_url');
        });
    }
};
