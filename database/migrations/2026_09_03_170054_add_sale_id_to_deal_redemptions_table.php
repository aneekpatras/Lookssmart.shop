<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 sub-step 3: `deal_redemptions` (Phase 2) only ever linked a redemption to a `booking_id` —
 * there was no POS sale channel yet. Without a `sale_id` here, a coupon applied at checkout would
 * never be counted toward `Deal.usage_limit`/`per_user_limit` by `PriceQuoteService`'s shared
 * `dealWithinLimits()`/`assertDealUsable()` checks (both query `$deal->redemptions()->count()`),
 * letting a limited-use coupon be redeemed at the register with no limit at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_redemptions', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('deal_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
