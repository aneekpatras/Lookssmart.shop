<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS sub-step 4 (thermal/A4 invoicing): the amount physically handed over for a cash payment,
     * distinct from `amount` (what was applied toward the sale total) so "Change Returned" can be
     * printed on a re-fetched receipt/invoice later instead of only existing transiently in the
     * Terminal's in-session state. Null for non-cash methods, where "tendered" has no meaning.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tendered_amount', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('tendered_amount');
        });
    }
};
