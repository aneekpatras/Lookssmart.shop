<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS sub-step 5: the cart-level manual %-discount/%-tax the cashier types directly (composed
     * additively with per-line discounts and coupons in `PriceQuoteService::quoteCart()`). Persisted
     * as their own columns — not just folded into the existing `discount`/`tax` dollar amounts — so a
     * resumed held sale can restore the exact percentage typed, and so the receipt/invoice can print
     * "Discount (12%)" rather than only a dollar figure.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            $table->decimal('tax_rate_percent', 5, 2)->nullable()->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'tax_rate_percent']);
        });
    }
};
