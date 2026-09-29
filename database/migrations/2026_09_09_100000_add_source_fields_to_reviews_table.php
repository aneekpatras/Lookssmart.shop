<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the fields needed to display genuine Google-sourced reviews alongside this app's own
 * organic (customer_id-linked) reviews on the homepage carousel — entirely additive, nothing here
 * changes how an organic review is created, moderated, or scored.
 *
 * `source` distinguishes the two kinds. A Google review has no `App\Models\User` behind it (nobody
 * signed in to leave it), so `reviewer_name`/`reviewer_category` give it a display name and a
 * treatment-category label without needing `customer_id`/`service_id` to be set — those stay
 * nullable and unused for `source = 'google'` rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('source')->default('organic')->after('status');
            $table->string('reviewer_name')->nullable()->after('customer_id');
            $table->string('reviewer_category')->nullable()->after('service_id');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'reviewer_name', 'reviewer_category']);
        });
    }
};
