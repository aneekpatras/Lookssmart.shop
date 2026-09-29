<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad hoc task 20: the gallery page's category grouping needs its own admin-controlled display
 * order, which the previous free-text `galleries.category` string had nowhere to store — this is
 * the normalized replacement, shaped exactly like the existing `service_categories` table
 * (`name`/`slug`/`sort`) plus a `subtitle` for the public page's per-section subtitle copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_categories');
    }
};
