<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same field/precedence as `services.stock_image_url` (see that migration), for deals: an admin can
 * paste a remote image URL instead of uploading a file for the feature image. A newly uploaded file
 * always wins over this when both are present in the same save — see `DealController::applyImage()`.
 * Deliberately a separate nullable column rather than repurposing `image_path`, since `image_path`
 * specifically holds a LOCAL disk path written by `SecureUploadService` and is read that way
 * everywhere else in the app (e.g. `asset("storage/{$deal->image_path}")`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('stock_image_url', 2048)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('stock_image_url');
        });
    }
};
