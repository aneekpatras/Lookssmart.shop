<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A remote stock-photo URL per service category, used as the card/banner image for every service in
 * that category until real salon photography is uploaded.
 *
 * This is NOT a revival of the `image_path` column dropped in Phase 6
 * (`2026_08_27_160000_add_sku_to_services_and_drop_image_path`). That one held a LOCAL path and was
 * genuinely superseded by spatie/medialibrary, which remains the only place real uploaded salon
 * images live. This column holds an EXTERNAL url for placeholder stock imagery, which medialibrary
 * deliberately cannot represent (it stores files on a disk, not references to third-party hosts).
 * The two coexist by precedence: a service's own uploaded medialibrary image always wins, and this
 * category-level stock image is only the fallback — see PublicWebsiteController::serviceData().
 *
 * Requires `https://images.unsplash.com` in the CSP's `img-src` (SecurityHeaders), since the app's
 * default policy is `img-src 'self' data:` and would otherwise block these silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('stock_image_url', 2048)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('stock_image_url');
        });
    }
};
