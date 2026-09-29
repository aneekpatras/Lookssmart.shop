<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the MARKETING/display fields the public Deals page and its admin form need, entirely
 * additive to the columns that already power the real discount engine (`type`, `value`, `code`,
 * `min_amount`, `usage_limit`, `per_user_limit`, `is_stackable`, `is_auto_apply`, `is_active`,
 * `starts_at`/`ends_at`, and the `services()`/`categories()` pivots) — `PriceQuoteService` never
 * reads anything added here, confirmed by reading it before writing this migration, so a deal's
 * real redemption behaviour cannot be affected by any of these columns.
 *
 * `original_price`/`deal_price` are DISPLAY prices for a package deal ("Rs. 45,000 → Rs. 30,000" on
 * a card) — deliberately separate from `value`/`type`, which are the actual discount MATH the
 * booking engine applies. The two are kept in sync by the seeder (`value` = `original_price` -
 * `deal_price`), but nothing enforces that at the database level, because an admin may reasonably
 * want to advertise a package price without it mapping to a literal cart-level discount formula.
 *
 * `included_services` is a free-text JSON list (the marketing checklist a card shows), distinct
 * from the `services()` pivot (which drives which real catalog services a coupon code is valid
 * against). A package deal's marketing copy does not have to enumerate every applicable service —
 * "Bridal hair, makeup and draping" reads better than three separate bullet points pulled from
 * catalog names — so this is deliberately free text with a fallback to the real attached services'
 * names when left empty, implemented in `PublicWebsiteController::dealShowcaseData()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            // One of the 6 named filter tabs. String, not enum: an enum migration is a schema
            // change every time the salon wants a 7th tag, a plain string with app-level validation
            // is not.
            $table->string('category_tag')->nullable()->after('subtitle');
            $table->decimal('original_price', 12, 2)->nullable()->after('value');
            $table->decimal('deal_price', 12, 2)->nullable()->after('original_price');
            $table->json('included_services')->nullable()->after('deal_price');
            $table->text('description')->nullable()->after('included_services');
            $table->text('terms')->nullable()->after('description');
            $table->string('image_path')->nullable()->after('terms');
            // Separate from `category_tag = 'Top Deals'`: a deal filed under Hair/Skin/etc. can
            // ALSO be pinned into the Top Deals section without reclassifying it — a curated
            // overlay, not a second taxonomy. PublicWebsiteController documents this precisely.
            $table->boolean('is_top_deal')->default(false)->after('is_active');

            $table->index('category_tag');
            $table->index('is_top_deal');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex(['category_tag']);
            $table->dropIndex(['is_top_deal']);
            $table->dropColumn([
                'subtitle',
                'category_tag',
                'original_price',
                'deal_price',
                'included_services',
                'description',
                'terms',
                'image_path',
                'is_top_deal',
            ]);
        });
    }
};
