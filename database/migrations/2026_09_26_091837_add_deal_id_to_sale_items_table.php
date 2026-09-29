<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 12 sub-step 8: a POS cart line is either a real Service (`service_id`, already nullable
     * — see that column's own migration) or a whole Deal package (`deal_id`, this column) — never
     * both. Adding a Deal in the terminal now prices it as ONE line (the deal's own bundled services
     * summed, not exploded into N separate lines), so this column is what a receipt/report reads to
     * tell "one deal package" apart from "one ordinary service."
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deal_id');
        });
    }
};
