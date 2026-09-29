<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 sub-step 2: "Archived" is an orthogonal view filter, not a `status` value — a replied or
 * spam message can also be archived. Additive nullable column instead of touching the `status` enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
