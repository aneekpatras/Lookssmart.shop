<?php

use App\Models\Gallery;
use App\Models\GalleryImage;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Slide;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 10 sub-step 5: a real catalog table for the Media Library, distinct from spatie/
 * laravel-medialibrary's own `media` table (Phase 6, used only by Service/ServiceCategory's
 * addMediaFromRequest() flow). Every file `SecureUploadService::storePublicImage()` stores — sliders,
 * blog covers, gallery photos, business logo/favicon — gets a row here going forward (wired directly
 * into that service, not duplicated at each of its 4 call sites). This migration also backfills rows
 * for every public-disk file already referenced by an existing Slide/Post/GalleryImage/Setting row
 * from sub-steps 1–4, so the library isn't empty of everything uploaded before this table existed.
 * Backfilled rows have no known uploader (`uploaded_by` null) and skip any path whose file is missing
 * from disk (a broken reference shouldn't crash the migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path')->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('mime_type');
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $paths = collect();

        Slide::query()->pluck('image_path')->each(fn ($path) => $paths->push($path));
        Slide::query()->whereNotNull('mobile_image_path')->pluck('mobile_image_path')->each(fn ($path) => $paths->push($path));
        Post::query()->whereNotNull('cover_image_path')->pluck('cover_image_path')->each(fn ($path) => $paths->push($path));
        GalleryImage::query()->pluck('image_path')->each(fn ($path) => $paths->push($path));
        GalleryImage::query()->whereNotNull('pair_image_path')->pluck('pair_image_path')->each(fn ($path) => $paths->push($path));

        if (Schema::hasTable('galleries')) {
            Gallery::query()->whereNotNull('cover_image_id')->with('coverImage')->get()
                ->each(fn (Gallery $gallery) => $gallery->coverImage && $paths->push($gallery->coverImage->image_path));
        }

        foreach (['business.logo_path', 'business.favicon_path'] as $key) {
            $setting = Setting::where('key', $key)->first();
            if ($setting?->value) {
                $paths->push($setting->value);
            }
        }

        $now = now();

        $paths->unique()->filter()->each(function (string $path) use ($now) {
            if (! Storage::disk('public')->exists($path)) {
                return;
            }

            DB::table('media_assets')->insertOrIgnore([
                'disk' => 'public',
                'path' => $path,
                'original_name' => basename($path),
                'mime_type' => Storage::disk('public')->mimeType($path) ?: null,
                'size' => Storage::disk('public')->size($path),
                'uploaded_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
